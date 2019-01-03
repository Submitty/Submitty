from argparse import Namespace
from io import StringIO
import sys
import tempfile
import unittest

from grading import json_syntax_checker


class TestJsonSyntaxChecker(unittest.TestCase):
    def test_valid_json(self):
        with tempfile.NamedTemporaryFile() as temp_file:
            temp_file.write(b'[0, 1, 2]')
            temp_file.flush()
            self.assertListEqual(
                [0, 1, 2],
                json_syntax_checker.validate_file(temp_file.name)
            )

    def test_invalid_json(self):
        with tempfile.NamedTemporaryFile() as temp_file:
            temp_file.write(b'invalid[JSON]')
            temp_file.flush()
            with self.assertRaises(ValueError) as cm:
                json_syntax_checker.validate_file(temp_file.name)
            self.assertEqual(
                'Expecting value: line 1 column 1 (char 0)',
                str(cm.exception)
            )

    def test_not_exist(self):
        with self.assertRaises(FileNotFoundError) as cm:
            json_syntax_checker.validate_file('/file/not/real/or/exists')
        self.assertEqual(
            'Cannot find JSON file to validate: /file/not/real/or/exists',
            str(cm.exception)
        )

    def test_not_a_file(self):
        with tempfile.TemporaryDirectory() as temp_dir:
            with self.assertRaises(json_syntax_checker.NotAFileError) as cm:
                json_syntax_checker.validate_file(temp_dir)
            self.assertEqual(
                'Not a file: {}'.format(temp_dir),
                str(cm.exception)
            )

    def test_parse_args_good(self):
        expected = Namespace()
        expected.file = 'test_file.json'
        self.assertEqual(expected, json_syntax_checker.parse_args(['test_file.json']))
    
    def test_parse_missing_arg(self):
        stderr = sys.stderr
        sys.stderr = StringIO()
        with self.assertRaises(SystemExit) as cm:
            json_syntax_checker.parse_args([])
        self.assertEqual('2', str(cm.exception))
        sys.stderr = stderr

    def test_arg_version(self):
        stdout = sys.stdout
        sys.stdout = StringIO()
        with self.assertRaises(SystemExit) as cm:
            json_syntax_checker.parse_args(['--version'])
        self.assertEqual('0', str(cm.exception))
        self.assertEqual('json_syntax_checker.py 1.0.0', sys.stdout.getvalue().strip())
        sys.stdout = stdout

    def test_valid_main(self):
        with tempfile.NamedTemporaryFile() as temp_file:
            temp_file.write(b'[0, 1, 2]')
            temp_file.flush()
            self.assertTrue(json_syntax_checker.main([temp_file.name]))

    def test_invalid_main(self):
        with tempfile.NamedTemporaryFile() as temp_file:
            temp_file.write(b'Invalid [JSON]')
            temp_file.flush()
            with self.assertRaises(SystemExit) as cm:
                self.assertTrue(json_syntax_checker.main([temp_file.name]))
            self.assertEqual(
                'Expecting value: line 1 column 1 (char 0)',
                str(cm.exception)
            )

if __name__ == '__main__':
    unittest.main()
