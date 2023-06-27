import random
import shutil
import sys
import tempfile
import unittest
from argparse import Namespace
from io import StringIO
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import patch

import migrator
from sqlalchemy.exc import OperationalError


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
        args = Namespace()
        args.environments = ['master']
        args.config = SimpleNamespace()
        args.config.database = dict()

        with patch.object(migrator.db, 'Database') as mock_class:
            migrator.main.load_triggers(args)

        self.assertTrue(mock_class.called)
        database = mock_class.return_value
        self.assertTrue(database.execute.called)
        self.assertTrue(database.commit.called)
        self.assertTrue(database.close.called)
        self.assertEqual(self.num_files, database.execute.call_count)

        data = [int(args[0][0]) for args in database.execute.call_args_list]
        self.assertEqual(set(self.data), set(data))

        self.assertEqual(
            'Loading trigger functions to master...\n  {}\n\nDONE\n'
                .format('\n  '.join(['test_fn_{}'.format(self.data.index(n)) for n in data])),
            sys.stdout.getvalue()
        )

    def test_no_trigger_dir(self):
        trigger_path = migrator.TRIGGERS_PATH
        migrator.TRIGGERS_PATH = Path(tempfile.mkstemp()[1])

        args = Namespace()
        args.environments = ['master']
        args.config = SimpleNamespace()
        args.config.database = dict()

        with self.assertRaises(SystemExit) as cm:
            migrator.main.load_triggers(args)
        self.assertEqual('Error: Could not find triggers directory', cm.exception.args[0])

        migrator.main.load_triggers(args, True)

        migrator.TRIGGERS_PATH.unlink()
        migrator.TRIGGERS_PATH = trigger_path
    
    def test_no_trigger_files(self):
        trigger_path = migrator.TRIGGERS_PATH
        migrator.TRIGGERS_PATH = Path(tempfile.mkdtemp())

        args = Namespace()
        args.environments = ['master']
        args.config = SimpleNamespace()
        args.config.database = dict()

        with patch.object(migrator.db, 'Database') as mock_class:
            migrator.main.load_triggers(args)
        
        self.assertFalse(mock_class.called)
        
        self.assertEqual('Loading trigger functions to master...DONE\n', sys.stdout.getvalue())
        
        migrator.TRIGGERS_PATH.rmdir()
        migrator.TRIGGERS_PATH = trigger_path
    
    def test_db_fail(self):
        args = Namespace()
        args.environments = ['master']
        args.config = SimpleNamespace()
        args.config.database = dict()

        with patch.object(migrator.db, 'Database') as mock_class:
            mock_class.side_effect = OperationalError(None, None, 'first\nsecond')
            with self.assertRaises(SystemExit) as cm:
                migrator.main.load_triggers(args)
        
        self.assertTrue(mock_class.called)
        self.assertEqual('Error applying triggers to master database:\n  first', cm.exception.args[0])