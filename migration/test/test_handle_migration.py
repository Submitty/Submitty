from argparse import Namespace
from io import StringIO
from pathlib import Path
import shutil
from sqlalchemy.exc import OperationalError
import sys
import tempfile
from types import SimpleNamespace
import unittest
from unittest.mock import patch

import migrator
from migrator import main


class TestHandleMigration(unittest.TestCase):
    def setUp(self):
        self.stdout = sys.stdout
        sys.stdout = StringIO()
        self.args = Namespace()
        self.dir = tempfile.mkdtemp()
        self.old_migrations_path = migrator.MIGRATIONS_PATH
        migrator.MIGRATIONS_PATH = Path(self.dir)
        self.databases = dict()

    def tearDown(self):
        sys.stdout = self.stdout
        shutil.rmtree(self.dir)
        migrator.MIGRATIONS_PATH = self.old_migrations_path

    def setup_test(self, environment, create=True):
        Path(self.dir, environment).mkdir()
        self.stdout = sys.stdout
        database = migrator.db.Database({'database_driver': 'sqlite'}, environment)
        if create:
            database.DynamicBase.metadata.create_all(database.engine)
        self.databases[environment] = database
    
    def test_no_course_dir(self):
        self.args.environments = ['course']
        self.args.config = SimpleNamespace()
        self.args.config.database = dict()
        self.args.config.submitty = {
            'submitty_data_dir': self.dir
        }
        main.handle_migration(self.args)
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
            mock_class.side_effect = OperationalError('test', None, None)
            main.handle_migration(self.args)
        self.assertEqual(
            "Database does not exist for master\n",
            sys.stdout.getvalue()
        )

    def test_status_no_db_system(self):
        self.args.environments = ['system']
        self.args.config = SimpleNamespace()
        self.args.config.database = dict()

        with patch.object(migrator.db, 'Database') as mock_class:
            mock_class.side_effect = OperationalError('test', None, None)
            main.handle_migration(self.args)
        self.assertEqual(
            "Database does not exist for system\n",
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
            main.handle_migration(self.args)
        self.assertEqual(
            "Submitty Database Migration Warning:  Database does not exist for semester=f19 course=csci1100\n",
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
            mock_class.side_effect = OperationalError('test', None, None)
            main.handle_migration(self.args)
        expected = """Database does not exist for master
Database does not exist for system
Submitty Database Migration Warning:  Database does not exist for semester=f19 course=csci1100
"""
        self.assertEqual(expected, sys.stdout.getvalue())

    @patch('migrator.main.migrate_environment')
    def test_status_master(self, mock_method):
        self.setup_test('master')
        self.args.environments = ['master']
        self.args.config = SimpleNamespace()
        self.args.config.database = dict()

        with patch.object(migrator.db, 'Database') as mock_class:
            mock_class.side_effect = [self.databases['master']]
            main.handle_migration(self.args)
        self.assertTrue(mock_class.called)
        self.assertTrue(mock_method.called)
        self.assertEqual(self.databases['master'], mock_method.call_args[0][0])
        self.assertEqual('master', mock_method.call_args[0][1])
        self.assertEqual(self.args, mock_method.call_args[0][2])
        self.assertFalse(self.databases['master'].open)

    @patch('migrator.main.migrate_environment')
    def test_status_system(self, mock_method):
        self.setup_test('system')
        self.args.environments = ['system']
        self.args.config = SimpleNamespace()
        self.args.config.database = dict()

        with patch.object(migrator.db, 'Database') as mock_class:
            mock_class.side_effect = [self.databases['system']]
            main.handle_migration(self.args)
        self.assertTrue(mock_class.called)
        self.assertTrue(mock_method.called)
        self.assertEqual(self.databases['system'], mock_method.call_args[0][0])
        self.assertEqual('system', mock_method.call_args[0][1])
        self.assertEqual(self.args, mock_method.call_args[0][2])
        self.assertFalse(self.databases['system'].open)

    @patch('migrator.main.migrate_environment')
    def test_status_course(self, mock_method):
        self.setup_test('course')
        self.args.environments = ['course']
        self.args.choose_course = None
        self.args.config = SimpleNamespace()
        self.args.config.database = dict()
        self.args.config.submitty = dict()
        self.args.config.submitty['submitty_data_dir'] = Path(self.dir)
        Path(self.dir, 'courses', 'f19', 'csci1100').mkdir(parents=True)

        with patch.object(migrator.db, 'Database') as mock_class:
            mock_class.side_effect = [self.databases['course']]
            main.handle_migration(self.args)
        self.assertTrue(mock_class.called)
        self.assertTrue(mock_method.called)
        self.assertEqual(self.databases['course'], mock_method.call_args[0][0])
        self.assertEqual('course', mock_method.call_args[0][1])
        self.assertEqual(self.args, mock_method.call_args[0][2])
        self.assertEqual(self.args.semester, 'f19')
        self.assertEqual(self.args.course, 'csci1100')
        self.assertFalse(self.databases['course'].open)
