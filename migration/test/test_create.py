"""Test the create command."""

from argparse import Namespace
from io import StringIO
from pathlib import Path
import shutil
import sys
import tempfile
import unittest

import migrator
import migrator.main


class TestCreate(unittest.TestCase):
    """Test the migrator."""

    def setUp(self):
        """Set up the temp directory to use for the tests."""
        self.stdout = sys.stdout
        sys.stdout = StringIO()
        self.dir = tempfile.mkdtemp()
        self.old_path = migrator.MIGRATIONS_PATH
        migrator.MIGRATIONS_PATH = Path(self.dir)

    def tearDown(self):
        """Remove the temp directory we used for the tests."""
        sys.stdout = self.stdout
        shutil.rmtree(self.dir)
        migrator.MIGRATIONS_PATH = self.old_path

    def create_test_runner(self, module_text, parameters, parameter_text, environment):
        """Run create test for a given environment."""
        args = Namespace()
        args.name = 'test'
        args.environments = [environment]
        migrator_dir = Path(self.dir, environment)
        migrator_dir.mkdir()
        migrator.main.create(args)
        expected = """\"\"\"{0}\"\"\"


def up({1}):
    \"\"\"
    Run up migration.

    {2}
    \"\"\"
    pass


def down({1}):
    \"\"\"
    Run down migration (rollback).

    {2}
    \"\"\"
    pass
""".format(module_text, ', '.join(parameters), parameter_text)
        found_files = 0
        for entry in migrator_dir.iterdir():
            with entry.open() as open_file:
                self.assertEqual(expected, open_file.read())
            found_files += 1
        self.assertEqual(1, found_files)

    def test_create_system(self):
        """Test the create commmand for system environment."""
        parameters = ['config']
        environment = 'system'
        module_text = 'Migration for the Submitty system.'
        parameter_text = """:param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config"""
        self.create_test_runner(module_text, parameters, parameter_text, environment)
        self.assertRegex(sys.stdout.getvalue(), r'Created migration: system\/[0-9]{14}_test.py')

    def test_create_master(self):
        """Test the create command for the master environment."""
        parameters = ['config', 'database']
        environment = 'master'
        module_text = 'Migration for the Submitty master database.'
        parameter_text = """:param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database"""
        self.create_test_runner(module_text, parameters, parameter_text, environment)
        self.assertRegex(sys.stdout.getvalue(), r'Created migration: master\/[0-9]{14}_test.py')

    def test_create_course(self):
        """Test the create command for the course environment."""
        parameters = ['config', 'database', 'semester', 'course']
        environment = 'course'
        module_text = 'Migration for a given Submitty course database.'
        parameter_text = """:param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    :param semester: Semester of the course being migrated
    :type semester: str
    :param course: Code of course being migrated
    :type course: str"""
        self.create_test_runner(module_text, parameters, parameter_text, environment)
        self.assertRegex(sys.stdout.getvalue(), r'Created migration: course\/[0-9]{14}_test.py')

    def test_create_master_and_system(self):
        args = Namespace()
        args.name = 'test'
        args.environments = ['system', 'master']
        for environment in args.environments:
            Path(self.dir, environment).mkdir()
        migrator.main.create(args)

        for environment in args.environments:
            found_files = 0
            for entry in Path(self.dir, environment).iterdir():
                with entry.open() as open_file:
                    self.assertTrue(len(open_file.read()) > 0)
                found_files += 1
            self.assertEqual(1, found_files)

        regex = r"""Created migration: master\/[0-9]{14}_test.py
Created migration: system\/[0-9]{14}_test.py"""
        self.assertRegex(sys.stdout.getvalue(), regex)

    def test_create_all(self):
        args = Namespace()
        args.name = 'test'
        args.environments = ['course', 'master', 'system']
        for environment in args.environments:
            Path(self.dir, environment).mkdir()
        migrator.main.create(args)

        for environment in args.environments:
            found_files = 0
            for entry in Path(self.dir, environment).iterdir():
                with entry.open() as open_file:
                    self.assertTrue(len(open_file.read()) > 0)
                found_files += 1
            self.assertEqual(1, found_files)

        regex = r"""Created migration: master\/[0-9]{14}_test.py
Created migration: system\/[0-9]{14}_test.py
Created migration: course\/[0-9]{14}_test.py"""
        self.assertRegex(sys.stdout.getvalue(), regex)

    def test_create_bad_name(self):
        args = Namespace()
        args.name = 'invalid#!!!'
        args.environments = ['system']
        with self.assertRaises(ValueError) as cm:
            migrator.main.create(args)
        self.assertEqual(
            "Invalid migration name (must only contain alphanumeric and _): invalid#!!!",
            str(cm.exception)
        )
