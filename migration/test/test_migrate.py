from argparse import Namespace
import unittest
from unittest.mock import patch

from migrator import main


class TestMigrate(unittest.TestCase):
    @patch('migrator.main.handle_migration')
    def test_migrate(self, mock_method):
        main.migrate(Namespace())
        args = Namespace()
        args.direction = 'up'
        self.assertTrue(mock_method.called)
        self.assertEqual(args, mock_method.call_args[0][0])
