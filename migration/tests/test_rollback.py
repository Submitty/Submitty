from argparse import Namespace
import unittest
from unittest.mock import patch

from migrator import main


class TestRollback(unittest.TestCase):
    @patch('migrator.main.handle_migration')
    def test_migrate(self, mock_method):
        main.rollback(Namespace())
        args = Namespace()
        args.direction = 'down'
        self.assertTrue(mock_method.called)
        self.assertEqual(args, mock_method.call_args[0][0])
