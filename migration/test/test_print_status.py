from argparse import Namespace
from io import StringIO
from pathlib import Path
import shutil
import sys
import tempfile

import unittest

from .helpers import create_migration

import migrator


class TestPrintStatus(unittest.TestCase):
    def setUp(self):
        self.stdout = sys.stdout
        sys.stdout = StringIO()
        self.args = Namespace()
        self.dir = tempfile.mkdtemp()
        self.old_migrations_path = migrator.MIGRATIONS_PATH
        migrator.MIGRATIONS_PATH = Path(self.dir)

    def tearDown(self):
        sys.stdout = self.stdout
        shutil.rmtree(self.dir)
        migrator.MIGRATIONS_PATH = self.old_migrations_path

    def setup_test(self, environment, args=None):
        Path(self.dir, environment).mkdir()
        self.database = migrator.db.Database({'database_driver': 'sqlite'}, environment)
        self.database.DynamicBase.metadata.create_all(self.database.engine)

    def test_status_none(self):
        self.setup_test('master')
        migrator.main.print_status(self.database, 'master', self.args)
        expected = """Status for master
MIGRATION                                                                   STATUS
----------------------------------------------------------------------------------

"""
        self.assertEqual(expected, sys.stdout.getvalue())

    def test_status_all_up(self):
        self.setup_test('master')
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
        self.setup_test('master')
        create_migration(self.database, self.dir, 'master', '01_test.py')
        create_migration(self.database, self.dir, 'master', '02_test.py', 1, False)
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
        self.setup_test('master')
        create_migration(self.database, self.dir, 'master', '01_test.py')
        create_migration(self.database, self.dir, 'master', '02_test.py', 1, False)
        create_migration(self.database, self.dir, 'master', '03_test.py', 0, True)
        migrator.main.print_status(self.database, 'master', self.args)
        expected = """Status for master
MIGRATION                                                                   STATUS
----------------------------------------------------------------------------------
01_test                                                                         UP
02_test                                                                    MISSING
03_test                                                                       DOWN

"""
        self.assertEqual(expected, sys.stdout.getvalue())

    def test_system(self):
        self.setup_test('system')
        create_migration(self.database, self.dir, 'system', '01_test.py')
        create_migration(self.database, self.dir, 'system', '02_test.py', 1, False)
        create_migration(self.database, self.dir, 'system', '03_test.py', 0, True)
        migrator.main.print_status(self.database, 'system', self.args)
        expected = """Status for system
MIGRATION                                                                   STATUS
----------------------------------------------------------------------------------
01_test                                                                         UP
02_test                                                                    MISSING
03_test                                                                       DOWN

"""
        self.assertEqual(expected, sys.stdout.getvalue())

    def test_status_course(self):
        self.setup_test('course')
        self.args.semester = 'f19'
        self.args.course = 'csci1000'
        create_migration(self.database, self.dir, 'course', '01_test.py')
        create_migration(self.database, self.dir, 'course', '02_test.py', 1, False)
        create_migration(self.database, self.dir, 'course', '03_test.py', 0, True)
        migrator.main.print_status(self.database, 'course', self.args)
        expected = """Status for f19.csci1000 (course)
MIGRATION                                                                   STATUS
----------------------------------------------------------------------------------
01_test                                                                         UP
02_test                                                                    MISSING
03_test                                                                       DOWN

"""
        self.assertEqual(expected, sys.stdout.getvalue())        
