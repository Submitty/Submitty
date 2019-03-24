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


class TestMigrate(unittest.TestCase):
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

    def test_migrate_system(self):
        environment = 'system'
        self.setup_test(environment)
        args = Namespace()
        args.direction = 'up'
        args.initial = False
        args.single = False
        args.config = SimpleNamespace()
        install_path = Path(self.dir, 'migrations', environment)
        install_path.mkdir(parents=True)
        args.config.submitty = {
            'submitty_install_dir': str(install_path)
        }

        create_migration(self.database, self.dir, environment, '01_test.py', status=0)
        create_migration(self.database, self.dir, environment, '02_test.py', status=0)
        migrator.main.migrate_environment(self.database, environment, args)
        self.assertEqual("""Running up migrations for system...  01_test
  02_test
DONE
""", sys.stdout.getvalue())

    def test_migrate_master(self):
        environment = 'master'
        self.setup_test(environment)
        args = Namespace()
        args.direction = 'up'
        args.initial = False
        args.single = False
        args.config = SimpleNamespace()
        install_path = Path(self.dir, 'migrations', environment)
        install_path.mkdir(parents=True)
        args.config.submitty = {
            'submitty_install_dir': str(install_path)
        }

        create_migration(self.database, self.dir, environment, '01_test.py', status=0)
        create_migration(self.database, self.dir, environment, '02_test.py', status=0)
        migrator.main.migrate_environment(self.database, environment, args)
        self.assertEqual("""Running up migrations for master...  01_test
  02_test
DONE
""", sys.stdout.getvalue())

    def test_migrate_course(self):
        environment = 'course'
        self.setup_test(environment)
        args = Namespace()
        args.direction = 'up'
        args.initial = False
        args.single = False
        args.semester = 'f18'
        args.course = 'csci1100'
        args.config = SimpleNamespace()
        install_path = Path(self.dir, 'migrations', environment)
        install_path.mkdir(parents=True)
        args.config.submitty = {
            'submitty_install_dir': str(install_path)
        }

        create_migration(self.database, self.dir, environment, '01_test.py', status=0)
        create_migration(self.database, self.dir, environment, '02_test.py', status=0)
        migrator.main.migrate_environment(self.database, environment, args)
        self.assertEqual("""Running up migrations for f18.csci1100...  01_test
  02_test
DONE
""", sys.stdout.getvalue())

    def test_missing_migrations(self):
        environment = 'master'
        self.setup_test(environment)
        args = Namespace()
        args.direction = 'up'
        args.single = False
        args.initial = False
        args.config = SimpleNamespace()
        install_path = Path(self.dir, 'migrations', environment)
        install_path.mkdir(parents=True)
        args.config.submitty = {
            'submitty_install_dir': str(install_path)
        }

        create_migration(self.database, self.dir, environment, '01_test.py', status=1)
        create_migration(self.database, self.dir, environment, '02_test.py', False, 1)
        create_migration(self.database, self.dir, environment, '03_test.py', status=0)
        create_migration(self.database, self.dir, environment, '04_test.py', status=0)
        migrator.main.migrate_environment(self.database, environment, args)
        self.assertEqual("""Running up migrations for master...
Removing 1 missing migrations:
  02_test

  03_test
  04_test
DONE

""", sys.stdout.getvalue())

    def test_fake_migrations(self):
        environment = 'master'
        self.setup_test(environment)
        args = Namespace()
        args.direction = 'up'
        args.set_fake = True
        args.single = False
        args.initial = False
        args.config = SimpleNamespace()
        install_path = Path(self.dir, 'migrations', environment)
        install_path.mkdir(parents=True)
        args.config.submitty = {
            'submitty_install_dir': str(install_path)
        }

        create_migration(self.database, self.dir, environment, '01_test.py', status=1)
        create_migration(self.database, self.dir, environment, '02_test.py', status=0)
        create_migration(self.database, self.dir, environment, '03_test.py', status=0)
        migrator.main.migrate_environment(self.database, environment, args)
        self.assertEqual("""Running up migrations for master...  02_test (FAKE)
  03_test (FAKE)
DONE
""", sys.stdout.getvalue())

    def test_single_migration(self):
        environment = 'master'
        self.setup_test(environment)
        args = Namespace()
        args.direction = 'up'
        args.single = True
        args.initial = False
        args.config = SimpleNamespace()
        install_path = Path(self.dir, 'migrations', environment)
        install_path.mkdir(parents=True)
        args.config.submitty = {
            'submitty_install_dir': str(install_path)
        }

        create_migration(self.database, self.dir, environment, '01_test.py', status=1)
        create_migration(self.database, self.dir, environment, '02_test.py', status=0)
        create_migration(self.database, self.dir, environment, '03_test.py', status=0)
        migrator.main.migrate_environment(self.database, environment, args)
        self.assertEqual("""Running up migrations for master...  02_test
DONE
""", sys.stdout.getvalue())

    def test_single_fake_migration(self):
        environment = 'master'
        self.setup_test(environment)
        args = Namespace()
        args.direction = 'up'
        args.set_fake = True
        args.single = True
        args.initial = False
        args.config = SimpleNamespace()
        install_path = Path(self.dir, 'migrations', environment)
        install_path.mkdir(parents=True)
        args.config.submitty = {
            'submitty_install_dir': str(install_path)
        }

        create_migration(self.database, self.dir, environment, '01_test.py', status=1)
        create_migration(self.database, self.dir, environment, '02_test.py', status=0)
        create_migration(self.database, self.dir, environment, '03_test.py', status=0)
        migrator.main.migrate_environment(self.database, environment, args)
        self.assertEqual("""Running up migrations for master...  02_test (FAKE)
DONE
""", sys.stdout.getvalue())

    def test_initial_migration(self):
        environment = 'master'
        self.setup_test(environment)
        args = Namespace()
        args.direction = 'up'
        args.single = False
        args.initial = True
        args.config = SimpleNamespace()
        install_path = Path(self.dir, 'migrations', environment)
        install_path.mkdir(parents=True)
        args.config.submitty = {
            'submitty_install_dir': str(install_path)
        }

        create_migration(self.database, self.dir, environment, '01_test.py', status=0)
        create_migration(self.database, self.dir, environment, '02_test.py', status=0)
        create_migration(self.database, self.dir, environment, '03_test.py', status=0)
        migrator.main.migrate_environment(self.database, environment, args)
        self.assertEqual("""Running up migrations for master...
  01_test
  02_test (FAKE)
  03_test (FAKE)
DONE

""", sys.stdout.getvalue())


if __name__ == '__main__':
    unittest.main()
