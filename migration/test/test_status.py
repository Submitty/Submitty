from argparse import Namespace
from io import StringIO
from pathlib import Path
import shutil
import sys
import tempfile
import unittest

from .helpers import create_migration

import migrator


class TestStatus(unittest.TestCase):
    def setUp(self):
        self.args = Namespace()
        self.dir = tempfile.mkdtemp()
        self.old_migrations_path = migrator.MIGRATIONS_PATH
        migrator.MIGRATIONS_PATH = Path(self.dir)
        Path(self.dir, 'master').mkdir()
        self.stdout = sys.stdout
        sys.stdout = StringIO()
        self.database = migrator.db.Database({'database_driver': 'sqlite'}, 'master')
        self.database.DynamicBase.metadata.create_all(self.database.engine)

    def tearDown(self):
        sys.stdout = self.stdout
        shutil.rmtree(self.dir)
        migrator.MIGRATIONS_PATH = self.old_migrations_path

    def test_status_none(self):
        migrator.main.print_status(self.database, 'master', self.args)
        expected = """Status for master
MIGRATION                                                                   STATUS
----------------------------------------------------------------------------------

"""
        self.assertEqual(expected, sys.stdout.getvalue())

    def test_status_all_up(self):
        create_migration(self.database, self.dir, 'master', '01_test.py')
        create_migration(self.database, self.dir, 'master', '02_test.py')
        create_migration(self.database, self.dir, 'master', '03_test.py')
        migrator.main.print_status(self.database, 'master', self.args)
        expected = """Status for master
MIGRATION                                                                   STATUS
----------------------------------------------------------------------------------
01_test                                                                         UP
02_test                                                                         UP
03_test                                                                         UP

"""
        self.assertEqual(expected, sys.stdout.getvalue())

    def test_one_missing(self):
        create_migration(self.database, self.dir, 'master', '01_test.py')
        create_migration(self.database, self.dir, 'master', '02_test.py', False)
        create_migration(self.database, self.dir, 'master', '03_test.py')
        migrator.main.print_status(self.database, 'master', self.args)
        expected = """Status for master
MIGRATION                                                                   STATUS
----------------------------------------------------------------------------------
01_test                                                                         UP
02_test                                                                    MISSING
03_test                                                                         UP

"""
        self.assertEqual(expected, sys.stdout.getvalue())

    def test_one_missing_one_down(self):
        create_migration(self.database, self.dir, 'master', '01_test.py')
        create_migration(self.database, self.dir, 'master', '02_test.py', False)
        create_migration(self.database, self.dir, 'master', '03_test.py', True, 0)
        migrator.main.print_status(self.database, 'master', self.args)
        expected = """Status for master
MIGRATION                                                                   STATUS
----------------------------------------------------------------------------------
01_test                                                                         UP
02_test                                                                    MISSING
03_test                                                                       DOWN

"""
        self.assertEqual(expected, sys.stdout.getvalue())


if __name__ == '__main__':
    unittest.main()
