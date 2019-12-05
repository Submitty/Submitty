import argparse
from io import StringIO
from pathlib import Path
import shutil
import sys
import tempfile
import unittest
from unittest.mock import patch
import migrator.cli


class ErrorRaisingArgumentParser(argparse.ArgumentParser):
    """
    Extension of ArgumentParser with catchable errors.

    ArgumentParser when it errors calls sys.exit(2) which
    we don't want when testing.
    """
    def error(self, message):
        raise ValueError(message)  # reraise an error so we can catch it


class TestCli(unittest.TestCase):
    def setUp(self):
        # Monkeypatch the ArgumentParser of our module
        migrator.cli.ArgumentParser = ErrorRaisingArgumentParser
        self.stderr = sys.stderr
        sys.stderr = StringIO()

    def tearDown(self):
        migrator.cli.ArgumentParser = argparse.ArgumentParser
        sys.stderr = self.stderr

    def test_no_args(self):
        with self.assertRaises(ValueError) as cm:
            migrator.cli.parse_args([])
        self.assertEqual("the following arguments are required: -c/--config, -e/--environment, command", str(cm.exception))

    def test_missing_environment_and_config(self):
        with self.assertRaises(ValueError) as cm:
            migrator.cli.parse_args(['migrate'])
        self.assertEqual('the following arguments are required: -c/--config, -e/--environment', str(cm.exception))

    def test_missing_environment_config_folder_exists(self):
        try:
            config_path = tempfile.mkdtemp()
            with self.assertRaises(ValueError) as cm:
                migrator.cli.parse_args(['migrate'], Path(config_path))
            self.assertEqual('the following arguments are required: -e/--environment', str(cm.exception))
        finally:
            shutil.rmtree(config_path)

    def test_bad_command(self):
        try:
            config_path = tempfile.mkdtemp()
            with self.assertRaises(ValueError) as cm:
                migrator.cli.parse_args(['-e', 'system', 'bad'], Path(config_path))
            self.assertEqual("argument command: invalid choice: 'bad' (choose from 'create', 'status', 'migrate', 'rollback')", str(cm.exception))
        finally:
            shutil.rmtree(config_path)

    def test_migrate(self):
        try:
            config_path = tempfile.mkdtemp()
            args = migrator.cli.parse_args(['-e', 'system', 'migrate'], Path(config_path))
            expected = argparse.Namespace()
            expected.choose_course = None
            expected.command = 'migrate'
            expected.config_path = Path(config_path).resolve()
            expected.environments = ['system']
            expected.initial = False
            expected.set_fake = False
            expected.single = False
            self.assertEqual(expected, args)
        finally:
            shutil.rmtree(config_path)

    def test_rollback(self):
        try:
            config_path = tempfile.mkdtemp()
            args = migrator.cli.parse_args(['-e', 'system', 'rollback'], Path(config_path))
            expected = argparse.Namespace()
            expected.choose_course = None
            expected.command = 'rollback'
            expected.config_path = Path(config_path).resolve()
            expected.environments = ['system']
            expected.set_fake = False
            self.assertEqual(expected, args)
        finally:
            shutil.rmtree(config_path)

    def test_status(self):
        try:
            config_path = tempfile.mkdtemp()
            args = migrator.cli.parse_args(['-e', 'system', 'status'], Path(config_path))
            expected = argparse.Namespace()
            expected.choose_course = None
            expected.command = 'status'
            expected.config_path = Path(config_path).resolve()
            expected.environments = ['system']
            self.assertEqual(expected, args)
        finally:
            shutil.rmtree(config_path)

    def test_create_no_name(self):
        try:
            config_path = tempfile.mkdtemp()
            with self.assertRaises(ValueError) as cm:
                migrator.cli.parse_args(['-e', 'system', 'create'], Path(config_path))
            self.assertEqual('the following arguments are required: name', str(cm.exception))
        finally:
            shutil.rmtree(config_path)

    def test_create(self):
        try:
            config_path = tempfile.mkdtemp()
            args = migrator.cli.parse_args(['-e', 'system', 'create', 'test'], Path(config_path))
            expected = argparse.Namespace()
            expected.choose_course = None
            expected.command = 'create'
            expected.config_path = Path(config_path).resolve()
            expected.environments = ['system']
            expected.name = 'test'
            self.assertEqual(expected, args)
        finally:
            shutil.rmtree(config_path)

    def test_multiple_environments(self):
        try:
            config_path = tempfile.mkdtemp()
            args = migrator.cli.parse_args(['-e', 'course', '-e', 'master', '-e', 'system', 'migrate'], Path(config_path))
            expected = argparse.Namespace()
            expected.choose_course = None
            expected.command = 'migrate'
            expected.config_path = Path(config_path).resolve()
            expected.environments = ['master', 'system', 'course']
            expected.initial = False
            expected.set_fake = False
            expected.single = False
            self.assertEqual(expected, args)
        finally:
            shutil.rmtree(config_path)

    def test_config_flag(self):
        try:
            config_path = tempfile.mkdtemp()
            args = migrator.cli.parse_args(['-c', str(config_path), '-e', 'system', 'migrate'])
            expected = argparse.Namespace()
            expected.choose_course = None
            expected.command = 'migrate'
            expected.config_path = Path(config_path).resolve()
            expected.environments = ['system']
            expected.initial = False
            expected.set_fake = False
            expected.single = False
            self.assertEqual(expected, args)
        finally:
            shutil.rmtree(config_path)

    def test_config_flag_and_argument(self):
        try:
            ignored_path = tempfile.mkdtemp()
            config_path = tempfile.mkdtemp()
            args = migrator.cli.parse_args(['-c', str(config_path), '-e', 'system', 'migrate'], Path(ignored_path))
            expected = argparse.Namespace()
            expected.choose_course = None
            expected.command = 'migrate'
            expected.config_path = Path(config_path).resolve()
            expected.environments = ['system']
            expected.initial = False
            expected.set_fake = False
            expected.single = False
            self.assertEqual(expected, args)
        finally:
            shutil.rmtree(config_path)

    @patch('migrator.main.migrate')
    def test_run_migrate(self, mock_method):
        try:
            config_path = Path(tempfile.mkdtemp()).resolve()
            with patch.object(migrator.cli, 'Config', return_value='config_object') as mock_class:
                migrator.cli.run(['-e', 'system', 'migrate'], config_path)
            self.assertTrue(mock_class.called)
            self.assertTrue(mock_method.called)
            expected = argparse.Namespace()
            expected.choose_course = None
            expected.command = 'migrate'
            expected.config_path = config_path.resolve()
            expected.environments = ['system']
            expected.initial = False
            expected.set_fake = False
            expected.single = False
            expected.config = 'config_object'
            self.assertEqual(expected, mock_method.call_args[0][0])
        finally:
            shutil.rmtree(str(config_path))

    @patch('migrator.main.rollback')
    def test_run_rollback(self, mock_method):
        try:
            config_path = Path(tempfile.mkdtemp()).resolve()
            with patch.object(migrator.cli, 'Config', return_value='config_object') as mock_class:
                migrator.cli.run(['-e', 'system', 'rollback'], config_path)
            self.assertTrue(mock_class.called)
            self.assertTrue(mock_method.called)
            expected = argparse.Namespace()
            expected.choose_course = None
            expected.command = 'rollback'
            expected.config_path = config_path.resolve()
            expected.environments = ['system']
            expected.set_fake = False
            expected.config = 'config_object'
            self.assertEqual(expected, mock_method.call_args[0][0])
        finally:
            shutil.rmtree(str(config_path))

    @patch('migrator.main.status')
    def test_run_status(self, mock_method):
        try:
            config_path = Path(tempfile.mkdtemp()).resolve()
            with patch.object(migrator.cli, 'Config', return_value='config_object') as mock_class:
                migrator.cli.run(['-e', 'system', 'status'], config_path)
            self.assertTrue(mock_class.called)
            self.assertTrue(mock_method.called)
            expected = argparse.Namespace()
            expected.choose_course = None
            expected.command = 'status'
            expected.config_path = config_path.resolve()
            expected.environments = ['system']
            expected.config = 'config_object'
            self.assertEqual(expected, mock_method.call_args[0][0])
        finally:
            shutil.rmtree(str(config_path))

    @patch('migrator.main.create')
    def test_run_create(self, mock_method):
        try:
            config_path = Path(tempfile.mkdtemp()).resolve()
            with patch.object(migrator.cli, 'Config', return_value='config_object') as mock_class:
                migrator.cli.run(['-e', 'system', 'create', 'test'], config_path)
            self.assertTrue(mock_class.called)
            self.assertTrue(mock_method.called)
            expected = argparse.Namespace()
            expected.choose_course = None
            expected.command = 'create'
            expected.name = 'test'
            expected.config_path = config_path.resolve()
            expected.environments = ['system']
            expected.config = 'config_object'
            self.assertEqual(expected, mock_method.call_args[0][0])
        finally:
            shutil.rmtree(str(config_path))
