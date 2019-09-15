from argparse import Namespace
import unittest

from migrator import main


class TestMain(unittest.TestCase):
    def test_noop(self):
        """Test that noop function can take variable amount of parameters."""
        test = []
        for i in range(5):
            main.noop(*test)
            test.append(i)

    def test_call_func_system(self):
        args = Namespace()
        args.config = 'a'

        def func(*func_args):
            self.assertEqual(1, len(func_args))
            self.assertEqual('a', func_args[0])

        main.call_func(func, None, 'system', args)

    def test_call_func_master(self):
        args = Namespace()
        args.config = 'b'

        def func(*func_args):
            self.assertEqual(2, len(func_args))
            self.assertEqual('b', func_args[0])
            self.assertEqual('c', func_args[1])

        main.call_func(func, 'c', 'master', args)

    def test_call_func_course(self):
        args = Namespace()
        args.config = 'd'
        args.semester = 'e'
        args.course = 'f'

        def func(*func_args):
            self.assertEqual(4, len(func_args))
            self.assertEqual('d', func_args[0])
            self.assertEqual('g', func_args[1])
            self.assertEqual('e', func_args[2])
            self.assertEqual('f', func_args[3])

        main.call_func(func, 'g', 'course', args)
