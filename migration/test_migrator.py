#!/usr/bin/env python3
"""Test suite for the migrator tool."""

from argparse import Namespace
from pathlib import Path
import shutil
import tempfile
import unittest

import migrator


class TestMigrator(unittest.TestCase):
    """Test the migrator."""

    def setUp(self):
        """Set up the temp directory to use for the tests."""
        self.dir = tempfile.mkdtemp()
        migrator.MIGRATIONS_PATH = Path(self.dir)

    def tearDown(self):
        """Remove the temp directory we used for the tests."""
        shutil.rmtree(self.dir)

    def create_test_runner(self, parameters, environment):
        """Run create test for a given environment."""
        args = Namespace()
        args.name = 'test'
        args.environments = [environment]
        migrator_dir = Path(self.dir, environment)
        migrator_dir.mkdir()
        migrator.create(args)
        expected = """def up({0}):
    pass


def down({0}):
    pass""".format(', '.join(parameters))
        for entry in migrator_dir.iterdir():
            with entry.open() as open_file:
                self.assertEqual(expected, open_file.read())

    def test_create_system(self):
        """Test the create commmand for system environment."""
        parameters = ['config']
        environment = 'system'
        self.create_test_runner(parameters, environment)

    def test_create_master(self):
        """Test the create command for the master environment."""
        parameters = ['config', 'conn']
        environment = 'master'
        self.create_test_runner(parameters, environment)

    def test_create_course(self):
        """Test the create command for the course environment."""
        parameters = ['config', 'conn', 'semester', 'course']
        environment = 'course'
        self.create_test_runner(parameters, environment)


if __name__ == '__main__':
    unittest.main()
