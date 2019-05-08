from argparse import Namespace
from io import StringIO
from pathlib import Path
import shutil
import sys
import tempfile
from types import SimpleNamespace
import unittest
from .helpers import create_migration

import migrator
import migrator.db
import migrator.main


class TestMigrateEnvironmentDown(unittest.TestCase):
    def setUp(self):
        self.stdout = sys.stdout
        sys.stdout = StringIO()
        self.dir = tempfile.mkdtemp()
        self.old_migrations_path = migrator.MIGRATIONS_PATH
        migrator.MIGRATIONS_PATH = Path(self.dir)

    def tearDown(self):
        sys.stdout = self.stdout
        shutil.rmtree(self.dir)
        migrator.MIGRATIONS_PATH = self.old_migrations_path
    
    def setup_test(self, environment):
        Path(self.dir, environment).mkdir()
        self.database = migrator.db.Database({'database_driver': 'sqlite'}, environment)
        self.database.DynamicBase.metadata.create_all(self.database.engine)

    def test_rollback_system(self):
        environment = 'system'
        self.setup_test(environment)
        args = Namespace()
        args.direction = 'down'
        args.config = None

        create_migration(self.database, self.dir, environment, '01_test1.py')
        create_migration(self.database, self.dir, environment, '02_test1.py')
        migrator.main.migrate_environment(self.database, environment, args)
        self.assertEqual("""Running down migrations for system...  02_test1
DONE
""", sys.stdout.getvalue())
        rows = self.database.session.query(self.database.migration_table).all()
        expected_rows = ['01_test1', '02_test1']
        self.assertEqual(len(rows), len(expected_rows))
        for i in range(len(rows)):
            row = rows[i]
            self.assertEqual(expected_rows[i], row.id)
            self.assertEqual(1 if i < 1 else 0, row.status)
            self.assertIsNotNone(row.commit_time)
            down_file = expected_rows[i] + '.py.down.txt'
            self.assertEqual(i == 1, Path(self.dir, down_file).exists())

    def test_rollback_master(self):
        environment = 'master'
        self.setup_test(environment)
        args = Namespace()
        args.direction = 'down'
        args.config = None

        create_migration(self.database, self.dir, environment, '01_test2.py')
        create_migration(self.database, self.dir, environment, '02_test2.py')
        migrator.main.migrate_environment(self.database, environment, args)
        self.assertEqual("""Running down migrations for master...  02_test2
DONE
""", sys.stdout.getvalue())
        rows = self.database.session.query(self.database.migration_table).all()
        expected_rows = ['01_test2', '02_test2']
        self.assertEqual(len(rows), len(expected_rows))
        for i in range(len(rows)):
            row = rows[i]
            self.assertEqual(expected_rows[i], row.id)
            self.assertEqual(1 if i < 1 else 0, row.status)
            self.assertIsNotNone(row.commit_time)
            down_file = expected_rows[i] + '.py.down.txt'
            self.assertEqual(i == 1, Path(self.dir, down_file).exists())

    def test_rollback_course(self):
        environment = 'course'
        self.setup_test(environment)
        args = Namespace()
        args.direction = 'down'
        args.semester = 'f18'
        args.course = 'csci1100'
        args.config = None

        create_migration(self.database, self.dir, environment, '01_test3.py')
        create_migration(self.database, self.dir, environment, '02_test3.py')
        migrator.main.migrate_environment(self.database, environment, args)
        self.assertEqual("""Running down migrations for f18.csci1100...  02_test3
DONE
""", sys.stdout.getvalue())
        rows = self.database.session.query(self.database.migration_table).all()
        expected_rows = ['01_test3', '02_test3']
        self.assertEqual(len(rows), len(expected_rows))
        for i in range(len(rows)):
            row = rows[i]
            self.assertEqual(expected_rows[i], row.id)
            self.assertEqual(1 if i < 1 else 0, row.status)
            self.assertIsNotNone(row.commit_time)
            down_file = expected_rows[i] + '.py.down.txt'
            self.assertEqual(i == 1, Path(self.dir, down_file).exists())
    
    def test_missing_migration(self):
        environment = 'master'
        self.setup_test(environment)
        args = Namespace()
        args.direction = 'down'
        args.config = SimpleNamespace()
        install_path = Path(self.dir, 'install')
        Path(install_path, 'migrations', environment).mkdir(parents=True)
        args.config.submitty = {
            'submitty_install_dir': str(install_path)
        }

        missing_migration = Path(install_path, 'migrations', environment, '02_test4.py')
        with missing_migration.open('w') as open_file:
            open_file.write("""
# pragma: no cover
from pathlib import Path
INSTALL_PATH = "{}"

def down(*_):
    with Path(INSTALL_PATH, 'test.txt').open('w') as open_file:
        open_file.write('test')
""".format(install_path))

        create_migration(self.database, self.dir, environment, '01_test4.py')
        create_migration(self.database, self.dir, environment, '02_test4.py', 1, False)
        create_migration(self.database, self.dir, environment, '03_test4.py')
        create_migration(self.database, self.dir, environment, '04_test4.py', 0)
        migrator.main.migrate_environment(self.database, environment, args)
        self.assertEqual("""Running down migrations for master...
Removing 1 missing migrations:
  02_test4

  03_test4
DONE

""", sys.stdout.getvalue())
        self.assertFalse(missing_migration.exists())
        self.assertTrue(Path(install_path, 'test.txt').exists())
        rows = self.database.session.query(self.database.migration_table).all()
        expected_rows = ['01_test4', '03_test4', '04_test4']
        self.assertEqual(len(rows), len(expected_rows))
        for i in range(len(rows)):
            row = rows[i]
            self.assertEqual(expected_rows[i], row.id)
            self.assertEqual(1 if i < 1 else 0, row.status)
            self.assertIsNotNone(row.commit_time)
            down_file = expected_rows[i] + '.py.down.txt'
            self.assertEqual(i == 1, Path(self.dir, down_file).exists())

    def test_missing_migration_no_file(self):
        environment = 'master'
        self.setup_test(environment)
        args = Namespace()
        args.direction = 'down'
        args.config = SimpleNamespace()
        install_path = Path(self.dir, 'install')
        Path(install_path, 'migrations', environment).mkdir(parents=True)
        args.config.submitty = {
            'submitty_install_dir': str(install_path)
        }

        create_migration(self.database, self.dir, environment, '01_test6.py')
        create_migration(self.database, self.dir, environment, '02_test6.py', 1, False)
        create_migration(self.database, self.dir, environment, '03_test6.py')
        create_migration(self.database, self.dir, environment, '04_test6.py', 0)
        migrator.main.migrate_environment(self.database, environment, args)
        self.assertEqual("""Running down migrations for master...
Removing 1 missing migrations:
  02_test6

  03_test6
DONE

""", sys.stdout.getvalue())
        rows = self.database.session.query(self.database.migration_table).all()
        expected_rows = ['01_test6', '03_test6', '04_test6']
        self.assertEqual(len(rows), len(expected_rows))
        for i in range(len(rows)):
            row = rows[i]
            self.assertEqual(expected_rows[i], row.id)
            self.assertEqual(1 if i < 1 else 0, row.status)
            self.assertIsNotNone(row.commit_time)
            down_file = expected_rows[i] + '.py.down.txt'
            self.assertEqual(i == 1, Path(self.dir, down_file).exists())

    def test_cannot_rollback_first_migration(self):
        environment = 'master'
        self.setup_test(environment)
        args = Namespace()
        args.direction = 'down'
        args.config = None

        create_migration(self.database, self.dir, environment, '01_test5.py')
        migrator.main.migrate_environment(self.database, environment, args)
        self.assertEqual("""Running down migrations for master...  Cannot rollback 01_test5
DONE
""", sys.stdout.getvalue())
        rows = self.database.session.query(self.database.migration_table).all()
        expected_rows = ['01_test5']
        self.assertEqual(len(rows), len(expected_rows))
        for i in range(len(rows)):
            row = rows[i]
            self.assertEqual(expected_rows[i], row.id)
            self.assertEqual(1, row.status)
            self.assertIsNotNone(row.commit_time)
            down_file = expected_rows[i] + '.py.down.txt'
            self.assertFalse(Path(self.dir, down_file).exists())
    
    def test_fake_rollback(self):
        environment = 'system'
        self.setup_test(environment)
        args = Namespace()
        args.direction = 'down'
        args.config = None
        args.set_fake = True

        create_migration(self.database, self.dir, environment, '01_test1.py')
        create_migration(self.database, self.dir, environment, '02_test1.py')
        migrator.main.migrate_environment(self.database, environment, args)
        self.assertEqual("""Running down migrations for system...  02_test1 (FAKE)
DONE
""", sys.stdout.getvalue())
        rows = self.database.session.query(self.database.migration_table).all()
        expected_rows = ['01_test1', '02_test1']
        self.assertEqual(len(rows), len(expected_rows))
        for i in range(len(rows)):
            row = rows[i]
            self.assertEqual(expected_rows[i], row.id)
            self.assertEqual(1 if i < 1 else 0, row.status)
            self.assertIsNotNone(row.commit_time)
            down_file = expected_rows[i] + '.py.down.txt'
            self.assertFalse(0, Path(self.dir, down_file).exists())
