from collections import OrderedDict
import json
from pathlib import Path
import shutil
import tempfile
import unittest
import migrator.config


class TestConfig(unittest.TestCase):
    def test_default_driver(self):
        self.assertEqual('psql', migrator.config.DEFAULT_DATABASE_DRIVER)

    def test_config(self):
        temp_dir = tempfile.mkdtemp()
        try:
            database_obj = OrderedDict({
                'username': 'test',
                'password': 'bob',
                'host': 'localhost',
                'database_driver': 'psql'
            })
            submitty_obj = OrderedDict({
                'install_dir': 'test',
                'data_dir': 'blue'
            })
            submitty_users_obj = OrderedDict({
                'dbuser': 'submitty_dbuser'
            })
            with Path(temp_dir, 'database.json').open('w') as open_file:
                json.dump(database_obj, open_file)
            with Path(temp_dir, 'submitty.json').open('w') as open_file:
                json.dump(submitty_obj, open_file)
            with Path(temp_dir, 'submitty_users.json').open('w') as open_file:
                json.dump(submitty_users_obj, open_file)
            config = migrator.config.Config(temp_dir)
            self.assertEqual(Path(temp_dir), config.config_path)
            self.assertDictEqual(database_obj, config.database)
            self.assertDictEqual(submitty_obj, config.submitty)
            self.assertDictEqual(submitty_users_obj, config.submitty_users)
        finally:
            shutil.rmtree(temp_dir)

    def test_default_driver_config(self):
        temp_dir = tempfile.mkdtemp()
        try:
            database_obj = OrderedDict({
                'username': 'test',
                'password': 'bob',
                'host': 'localhost'
            })
            submitty_obj = OrderedDict({
                'install_dir': 'test',
                'data_dir': 'blue'
            })
            submitty_users_obj = OrderedDict({
                'dbuser': 'submitty_dbuser'
            })
            with Path(temp_dir, 'database.json').open('w') as open_file:
                json.dump(database_obj, open_file)
            with Path(temp_dir, 'submitty.json').open('w') as open_file:
                json.dump(submitty_obj, open_file)
            with Path(temp_dir, 'submitty_users.json').open('w') as open_file:
                json.dump(submitty_users_obj, open_file)

            config = migrator.config.Config(temp_dir)
            database_obj['database_driver'] = 'psql'
            self.assertDictEqual(database_obj, config.database)
        finally:
            shutil.rmtree(temp_dir)
