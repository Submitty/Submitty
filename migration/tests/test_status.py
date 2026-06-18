from argparse import Namespace
from copy import deepcopy
from io import StringIO
from pathlib import Path
import shutil
from sqlalchemy.exc import OperationalError
import sys
import tempfile
from types import SimpleNamespace
import unittest
from unittest.mock import patch

from .helpers import create_migration

import migrator
from migrator import main


class TestStatus(unittest.TestCase):
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

    def setup_test(self, environment):
        Path(self.dir, environment).mkdir()

    def create_database(self, environment, create=True):
        database = migrator.db.Database({'database_driver': 'sqlite'}, environment)
        if create is True:
            database.DynamicBase.metadata.create_all(database.engine)
        return database

    def test_no_course_dir(self):
        self.args.environments = ['course']
        self.args.config = SimpleNamespace()
        self.args.config.database = dict()
        self.args.config.submitty = {
            'submitty_data_dir': self.dir
        }
        main.status(self.args)
        self.assertEqual(
            "Could not find courses directory: {}\n".format(
                str(Path(self.dir, 'courses'))
            ),
            sys.stdout.getvalue()
        )

    def test_status_no_db_master(self):
        self.args.environments = ['master']
        self.args.config = SimpleNamespace()
        self.args.config.database = dict()

        with patch.object(migrator.db, 'Database') as mock_class:
            mock_class.side_effect = OperationalError('test', None, "No Database")
            main.status(self.args)
        self.assertEqual(
            "Could not get database for migrations for master:\n  (builtins.str) No Database\n",
            sys.stdout.getvalue()
        )

    def test_status_no_db_system(self):
        self.args.environments = ['system']
        self.args.config = SimpleNamespace()
        self.args.config.database = dict()

        with patch.object(migrator.db, 'Database') as mock_class:
            mock_class.side_effect = OperationalError('test', None, "No Database")
            main.status(self.args)
        self.assertEqual(
            "Could not get database for migrations for system:\n  (builtins.str) No Database\n",
            sys.stdout.getvalue()
        )

    def test_status_no_db_course(self):
        self.args.environments = ['course']
        self.args.choose_course = None
        self.args.config = SimpleNamespace()
        self.args.config.database = dict()
        self.args.config.submitty = dict()
        self.args.config.submitty['submitty_data_dir'] = Path(self.dir)
        Path(self.dir, 'courses', 'f19', 'csci1100').mkdir(parents=True)

        with patch.object(migrator.db, 'Database') as mock_class:
            mock_class.side_effect = OperationalError('test', None, None)
            main.status(self.args)
        self.assertEqual(
            "Could not get the status for the migrations for f19.csci1100\n",
            sys.stdout.getvalue()
        )

    def test_status_no_db_all(self):
        self.args.environments = ['course', 'master', 'system']
        self.args.choose_course = None
        self.args.config = SimpleNamespace()
        self.args.config.database = dict()
        self.args.config.submitty = dict()
        self.args.config.submitty['submitty_data_dir'] = Path(self.dir)
        Path(self.dir, 'courses', 'f19', 'csci1100').mkdir(parents=True)

        with patch.object(migrator.db, 'Database') as mock_class:
            mock_class.side_effect = OperationalError('test', None, "No Database")
            main.status(self.args)
        expected = """Could not get database for migrations for master:
  (builtins.str) No Database
Could not get database for migrations for system:
  (builtins.str) No Database
Could not get the status for the migrations for f19.csci1100
"""
        self.assertEqual(expected, sys.stdout.getvalue())

    def test_status_no_table_master(self):
        self.setup_test('master')
        database = self.create_database('master', False)
        self.args.environments = ['master']
        self.args.config = SimpleNamespace()
        self.args.config.database = dict()

        with patch.object(migrator.db, 'Database') as mock_class:
            mock_class.side_effect = [database]
            main.status(self.args)
        self.assertEqual(
            "Could not find migration table for master\n",
            sys.stdout.getvalue()
        )
        self.assertFalse(database.open)

    def test_status_no_table_system(self):
        self.setup_test('system')
        database = self.create_database('system', False)
        self.args.environments = ['system']
        self.args.config = SimpleNamespace()
        self.args.config.database = dict()

        with patch.object(migrator.db, 'Database') as mock_class:
            mock_class.side_effect = [database]
            main.status(self.args)
        self.assertTrue(mock_class.called)
        self.assertEqual(
            "Could not find migration table for system\n",
            sys.stdout.getvalue()
        )
        self.assertFalse(database.open)

    def test_status_no_table_course(self):
        self.setup_test('course')
        database = self.create_database('course', False)
        self.args.environments = ['course']
        self.args.choose_course = None
        self.args.config = SimpleNamespace()
        self.args.config.database = dict()
        self.args.config.submitty = dict()
        self.args.config.submitty['submitty_data_dir'] = Path(self.dir)
        Path(self.dir, 'courses', 'f19', 'csci1100').mkdir(parents=True)

        with patch.object(migrator.db, 'Database') as mock_class:
            mock_class.side_effect = [database]
            main.status(self.args)
        self.assertTrue(mock_class.called)
        self.assertEqual(
            "Could not find migration table for f19.csci1100\n",
            sys.stdout.getvalue()
        )
        self.assertFalse(database.open)

    def test_status_no_table_all(self):
        self.setup_test('master')
        database_1 = self.create_database('master', False)
        self.setup_test('system')
        database_2 = self.create_database('system', False)
        self.setup_test('course')
        database_3 = self.create_database('course', False)
        self.args.environments = ['system', 'course', 'master']
        self.args.choose_course = None
        self.args.config = SimpleNamespace()
        self.args.config.database = dict()
        self.args.config.submitty = dict()
        self.args.config.submitty['submitty_data_dir'] = Path(self.dir)
        Path(self.dir, 'courses', 'f19', 'csci1100').mkdir(parents=True)

        with patch.object(migrator.db, 'Database') as mock_class:
            mock_class.side_effect = [
                database_1,
                database_2,
                database_3
            ]
            main.status(self.args)
        expected = """Could not find migration table for master
Could not find migration table for system
Could not find migration table for f19.csci1100
"""
        self.assertEqual(expected, sys.stdout.getvalue())
        self.assertFalse(database_1.open)
        self.assertFalse(database_2.open)
        self.assertFalse(database_3.open)

    @patch('migrator.main.print_status')
    def test_status_master(self, mock_method):
        self.setup_test('master')
        database = self.create_database('master')
        self.args.environments = ['master']
        self.args.config = SimpleNamespace()
        self.args.config.database = dict()

        with patch.object(migrator.db, 'Database') as mock_class:
            mock_class.side_effect = [database]
            main.status(self.args)
        self.assertTrue(mock_class.called)
        self.assertTrue(mock_method.called)
        self.assertEqual(database, mock_method.call_args[0][0])
        self.assertEqual('master', mock_method.call_args[0][1])
        # Test that mutation did not happen
        self.assertEqual(self.args.config.database, dict())
        self.args.config.database = {'dbname': 'submitty'}
        self.assertEqual(self.args, mock_method.call_args[0][2])
        self.assertFalse(database.open)

    @patch('migrator.main.print_status')
    def test_status_system(self, mock_method):
        self.setup_test('system')
        database = self.create_database('system')
        self.args.environments = ['system']
        self.args.config = SimpleNamespace()
        self.args.config.database = dict()

        with patch.object(migrator.db, 'Database') as mock_class:
            mock_class.side_effect = [database]
            main.status(self.args)
        self.assertTrue(mock_class.called)
        self.assertEqual(1, mock_class.call_count)
        self.assertTrue(({'dbname': 'submitty'}, 'system'), mock_class.call_args[0])
        self.assertTrue(mock_method.called)
        self.assertEqual(database, mock_method.call_args[0][0])
        self.assertEqual('system', mock_method.call_args[0][1])
        # Test that mutation did not happen
        self.assertEqual(self.args.config.database, dict())
        self.args.config.database = {'dbname': 'submitty'}
        self.assertEqual(self.args, mock_method.call_args[0][2])
        self.assertFalse(database.open)

    @patch('migrator.main.print_status')
    def test_status_course(self, mock_method):
        self.setup_test('course')
        database = self.create_database('course')
        self.args.environments = ['course']
        self.args.choose_course = None
        self.args.config = SimpleNamespace()
        self.args.config.database = dict()
        self.args.config.submitty = dict()
        self.args.config.submitty['submitty_data_dir'] = Path(self.dir)
        Path(self.dir, 'courses', 'f19', 'csci1100').mkdir(parents=True)

        with patch.object(migrator.db, 'Database') as mock_class:
            mock_class.side_effect = [database]
            main.status(self.args)
        self.assertTrue(mock_class.called)
        self.assertEqual(1, mock_class.call_count)
        self.assertTrue(
            ({'dbname': 'submitty_f19_csci1100'}, 'course'),
            mock_class.call_args[0]
        )
        self.assertTrue(mock_method.called)
        self.assertEqual(database, mock_method.call_args[0][0])
        self.assertEqual('course', mock_method.call_args[0][1])
        # Test that mutation did not happen
        self.assertEqual(self.args.config.database, dict())
        self.assertNotIn('semester', self.args)
        self.assertNotIn('course', self.args)
        self.args.config.database = {'dbname': 'submitty_f19_csci1100'}
        self.args.semester = 'f19'
        self.args.course = 'csci1100'
        self.assertEqual(self.args, mock_method.call_args[0][2])
        self.assertEqual(self.args.semester, 'f19')
        self.assertEqual(self.args.course, 'csci1100')
        self.assertFalse(database.open)

    @patch('migrator.main.print_status')
    def test_status_multiple_course(self, mock_method):
        self.setup_test('course')
        database_1 = self.create_database('course')
        database_2 = self.create_database('coures')
        database_3 = self.create_database('course')
        self.args.environments = ['course']
        self.args.choose_course = None
        self.args.config = SimpleNamespace()
        self.args.config.database = dict()
        self.args.config.submitty = dict()
        self.args.config.submitty['submitty_data_dir'] = Path(self.dir)
        Path(self.dir, 'courses', 'f18', 'csci1100').mkdir(parents=True)
        Path(self.dir, 'courses', 'f19', 'csci1100').mkdir(parents=True)
        Path(self.dir, 'courses', 'f19', 'csci1200').mkdir(parents=True)

        with patch.object(migrator.db, 'Database') as mock_class:
            mock_class.side_effect = [
                database_1,
                database_2,
                database_3
            ]
            main.status(self.args)
        self.assertTrue(mock_class.called)
        self.assertEqual(3, mock_class.call_count)
        self.assertTrue(
            ({'dbname': 'submitty_f18_csci1100'}, 'course'),
            mock_class.call_args_list[0][0]
        )
        self.assertTrue(
            ({'dbname': 'submitty_f19_csci1100'}, 'course'),
            mock_class.call_args_list[1][0]
        )
        self.assertTrue(
            ({'dbname': 'submitty_f19_csci1200'}, 'course'),
            mock_class.call_args_list[2][0]
        )
        self.assertTrue(mock_method.called)
        self.assertEqual(3, mock_method.call_count)

        mock_args = mock_method.call_args_list[0][0]
        expected_args = deepcopy(self.args)
        self.assertEqual(database_1, mock_args[0])
        self.assertEqual('course', mock_args[1])
        # Test that mutation did not happen
        self.assertEqual(expected_args.config.database, dict())
        self.assertNotIn('semester', expected_args)
        self.assertNotIn('course', expected_args)
        expected_args.config.database = {'dbname': 'submitty_f18_csci1100'}
        expected_args.semester = 'f18'
        expected_args.course = 'csci1100'
        self.assertEqual(expected_args, mock_args[2])
        self.assertEqual(expected_args.semester, 'f18')
        self.assertEqual(expected_args.course, 'csci1100')
        self.assertFalse(database_1.open)

        mock_args = mock_method.call_args_list[1][0]
        expected_args = deepcopy(self.args)
        self.assertEqual(database_2, mock_args[0])
        self.assertEqual('course', mock_args[1])
        # Test that mutation did not happen
        self.assertEqual(expected_args.config.database, dict())
        self.assertNotIn('semester', expected_args)
        self.assertNotIn('course', expected_args)
        expected_args.config.database = {'dbname': 'submitty_f19_csci1100'}
        expected_args.semester = 'f19'
        expected_args.course = 'csci1100'
        self.assertEqual(expected_args, mock_args[2])
        self.assertEqual(expected_args.semester, 'f19')
        self.assertEqual(expected_args.course, 'csci1100')
        self.assertFalse(database_2.open)

        mock_args = mock_method.call_args_list[2][0]
        expected_args = deepcopy(self.args)
        self.assertEqual(database_3, mock_args[0])
        self.assertEqual('course', mock_args[1])
        # Test that mutation did not happen
        self.assertEqual(expected_args.config.database, dict())
        self.assertNotIn('semester', expected_args)
        self.assertNotIn('course', expected_args)
        expected_args.config.database = {'dbname': 'submitty_f19_csci1200'}
        expected_args.semester = 'f19'
        expected_args.course = 'csci1200'
        self.assertEqual(expected_args, mock_args[2])
        self.assertEqual(expected_args.semester, 'f19')
        self.assertEqual(expected_args.course, 'csci1200')
        self.assertFalse(database_3.open)

    @patch('migrator.main.print_status')
    def test_status_choose_course(self, mock_method):
        self.setup_test('course')
        database_1 = self.create_database('course')
        self.args.environments = ['course']
        self.args.choose_course = ['f19', 'csci1100']
        self.args.config = SimpleNamespace()
        self.args.config.database = dict()
        self.args.config.submitty = dict()
        self.args.config.submitty['submitty_data_dir'] = Path(self.dir)
        Path(self.dir, 'courses', 'f18', 'csci1100').mkdir(parents=True)
        Path(self.dir, 'courses', 'f19', 'csci1100').mkdir(parents=True)
        Path(self.dir, 'courses', 'f19', 'csci1200').mkdir(parents=True)
        with patch.object(migrator.db, 'Database') as mock_class:
            mock_class.side_effect = [
                database_1
            ]
            main.status(self.args)
        self.assertTrue(mock_class.called)
        self.assertEqual(1, mock_class.call_count)
        self.assertTrue(
            ({'dbname': 'submitty_f19_csci1100'}, 'course'),
            mock_class.call_args_list[0][0]
        )
        self.assertTrue(mock_method.called)
        self.assertEqual(1, mock_method.call_count)

        mock_args = mock_method.call_args_list[0][0]
        expected_args = deepcopy(self.args)
        self.assertEqual(database_1, mock_args[0])
        self.assertEqual('course', mock_args[1])
        # Test that mutation did not happen
        self.assertEqual(expected_args.config.database, dict())
        self.assertNotIn('semester', expected_args)
        self.assertNotIn('course', expected_args)
        expected_args.config.database = {'dbname': 'submitty_f19_csci1100'}
        expected_args.semester = 'f19'
        expected_args.course = 'csci1100'
        self.assertEqual(expected_args, mock_args[2])
        self.assertEqual(expected_args.semester, 'f19')
        self.assertEqual(expected_args.course, 'csci1100')
        self.assertFalse(database_1.open)
