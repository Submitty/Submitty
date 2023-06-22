from argparse import Namespace
from types import SimpleNamespace
from io import StringIO
from pathlib import Path
import sys
import random
import tempfile
import shutil
import unittest
from unittest.mock import patch

import migrator

class TestLoadTriggers(unittest.TestCase):
    def setUp(self):
        self.stdout = sys.stdout
        sys.stdout = StringIO()
        self.dir = tempfile.mkdtemp()
        self.old_triggers_path = migrator.TRIGGERS_PATH
        migrator.TRIGGERS_PATH = Path(self.dir)
        self.num_files = 4
        self.data = [random.randint(0, 100000) for _ in range(0, self.num_files)]
        for i in range(0, self.num_files):
            with (migrator.TRIGGERS_PATH / "test_fn_{}.sql".format(i)).open('w') as file:
                file.write(str(self.data[i]))

    def tearDown(self):
        sys.stdout = self.stdout
        shutil.rmtree(self.dir)
        migrator.TRIGGERS_PATH = self.old_triggers_path
    
    def test_load_triggers(self):
        with patch.object(migrator.db, 'Database') as mock_class:
            args = Namespace()
            args.environments = ['master']
            args.config = SimpleNamespace()
            args.config.database = dict()

            migrator.main.load_triggers(args)

            self.assertTrue(mock_class.called)
            database = mock_class.return_value
            self.assertTrue(database.execute.called)
            self.assertTrue(database.close.called)
            self.assertEqual(self.num_files, database.execute.call_count)
            self.assertEqual(self.data, [int(args[0][0]) for args in reversed(database.execute.call_args_list)])