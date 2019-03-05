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


class TestRollback(unittest.TestCase):
    def setUp(self):
        self.dir = tempfile.mkdtemp()
        self.old_migrations_path = migrator.MIGRATIONS_PATH
        migrator.MIGRATIONS_PATH = Path(self.dir)

    def tearDown(self):
        sys.stdout = self.stdout
        shutil.rmtree(self.dir)
        migrator.MIGRATIONS_PATH = self.old_migrations_path
    
    def setup_test(self, environment):
        Path(self.dir, environment).mkdir()
        self.stdout = sys.stdout
        sys.stdout = StringIO()
        self.database = migrator.db.Database({'database_driver': 'sqlite'}, environment)
        self.database.DynamicBase.metadata.create_all(self.database.engine)

    def test_rollback_system(self):
        environment = 'system'
        self.setup_test(environment)
        args = Namespace()
        args.direction = 'down'
        args.config = SimpleNamespace()
        install_path = Path(self.dir, 'migrations', environment)
        install_path.mkdir(parents=True)
        args.config.submitty = {
            'submitty_install_dir': str(install_path)
        }

        create_migration(self.database, self.dir, environment, '01_test.py', status=1)
        create_migration(self.database, self.dir, environment, '02_test.py', status=1)
        migrator.main.migrate_environment(self.database, environment, args)
        self.assertEqual("""Running down migrations for system...  02_test
DONE
""", sys.stdout.getvalue())

    def test_rollback_master(self):
        environment = 'master'
        self.setup_test(environment)
        args = Namespace()
        args.direction = 'down'
        args.config = SimpleNamespace()
        install_path = Path(self.dir, 'migrations', environment)
        install_path.mkdir(parents=True)
        args.config.submitty = {
            'submitty_install_dir': str(install_path)
        }

        create_migration(self.database, self.dir, environment, '01_test.py', status=1)
        create_migration(self.database, self.dir, environment, '02_test.py', status=1)
        migrator.main.migrate_environment(self.database, environment, args)
        self.assertEqual("""Running down migrations for master...  02_test
DONE
""", sys.stdout.getvalue())

    def test_rollback_course(self):
        environment = 'course'
        self.setup_test(environment)
        args = Namespace()
        args.direction = 'down'
        args.semester = 'f18'
        args.course = 'csci1100'
        args.config = SimpleNamespace()
        install_path = Path(self.dir, 'migrations', environment)
        install_path.mkdir(parents=True)
        args.config.submitty = {
            'submitty_install_dir': str(install_path)
        }

        create_migration(self.database, self.dir, environment, '01_test.py', status=1)
        create_migration(self.database, self.dir, environment, '02_test.py', status=1)
        migrator.main.migrate_environment(self.database, environment, args)
        self.assertEqual("""Running down migrations for f18.csci1100...  02_test
DONE
""", sys.stdout.getvalue())

    def test_missing_migrations(self):
        environment = 'master'
        self.setup_test(environment)
        args = Namespace()
        args.direction = 'down'
        args.config = SimpleNamespace()
        install_path = Path(self.dir, 'migrations', environment)
        install_path.mkdir(parents=True)
        args.config.submitty = {
            'submitty_install_dir': str(install_path)
        }

        create_migration(self.database, self.dir, environment, '01_test.py', status=1)
        create_migration(self.database, self.dir, environment, '02_test.py', False, 1)
        create_migration(self.database, self.dir, environment, '03_test.py', status=1)
        create_migration(self.database, self.dir, environment, '04_test.py', status=0)
        migrator.main.migrate_environment(self.database, environment, args)
        self.assertEqual("""Running down migrations for master...
Removing 1 missing migrations:
  02_test

  03_test
DONE

""", sys.stdout.getvalue())

    def test_cannot_rollback_first_migration(self):
        environment = 'master'
        self.setup_test(environment)
        args = Namespace()
        args.direction = 'down'
        args.config = SimpleNamespace()
        install_path = Path(self.dir, 'migrations', environment)
        install_path.mkdir(parents=True)
        args.config.submitty = {
            'submitty_install_dir': str(install_path)
        }

        create_migration(self.database, self.dir, environment, '01_test.py', status=1)
        migrator.main.migrate_environment(self.database, environment, args)
        self.assertEqual("""Running down migrations for master...  Cannot rollback 01_test
DONE
""", sys.stdout.getvalue())


if __name__ == '__main__':
    unittest.main()
