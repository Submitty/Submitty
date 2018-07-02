from os import path, mkdir
from pathlib import Path
import shutil
import tempfile
import types
import unittest

from submitty_utils import glob


class TestGlob(unittest.TestCase):
    def setUp(self):
        self.dir = tempfile.mkdtemp()
        Path(path.join(self.dir, 'a')).touch()
        Path(path.join(self.dir, 'b')).touch()
        mkdir(path.join(self.dir, 'c'))
        mkdir(path.join(self.dir, 'd'))
        Path(path.join(self.dir, 'c', 'e')).touch()
        mkdir(path.join(self.dir, 'd', 'f'))
        Path(path.join(self.dir, 'd', 'f', 'g')).touch()

    def test_glob(self):
        expected = [path.join(self.dir, x) for x in ['a', 'b', 'c', 'd']]
        actual = glob.glob(path.join(self.dir, '**'))
        self.assertIsInstance(actual, list)
        self.assertCountEqual(expected, actual)

        actual2 = glob.glob(path.join(self.dir, '*'))
        self.assertIsInstance(actual2, list)
        self.assertEqual(actual, actual2)

    def test_iglob(self):
        expected = [path.join(self.dir, x) for x in ['a', 'b', 'c', 'd']]
        actual = glob.iglob(path.join(self.dir, '**'))
        self.assertIsInstance(actual, types.GeneratorType)
        actual = list(actual)
        self.assertCountEqual(expected, actual)

        actual2 = glob.iglob(path.join(self.dir, '*'))
        self.assertIsInstance(actual2, types.GeneratorType)
        actual2 = list(actual2)
        self.assertEqual(actual, actual2)

    def test_glob_recursive_star(self):
        expected = ['a', 'b', 'c', 'd']
        expected = [path.join(self.dir, x) for x in expected]
        actual = glob.glob(path.join(self.dir, '*'), recursive=True)
        self.assertIsInstance(actual, list)
        self.assertCountEqual(expected, actual)

    def test_glob_recursive(self):
        expected = ['', 'a', 'b', 'c', 'd', path.join('c', 'e'), path.join('d', 'f'), path.join('d', 'f', 'g')]
        expected = [path.join(self.dir, x) for x in expected]
        actual = glob.glob(path.join(self.dir, '**'), recursive=True)
        self.assertIsInstance(actual, list)
        self.assertCountEqual(expected, actual)

    def test_iglob_recursive_star(self):
        expected = ['a', 'b', 'c', 'd']
        expected = [path.join(self.dir, x) for x in expected]
        actual = glob.iglob(path.join(self.dir, '*'), recursive=True)
        self.assertIsInstance(actual, types.GeneratorType)
        self.assertCountEqual(expected, list(actual))

    def test_iglob_recursive(self):
        expected = ['', 'a', 'b', 'c', 'd', path.join('c', 'e'), path.join('d', 'f'), path.join('d', 'f', 'g')]
        expected = [path.join(self.dir, x) for x in expected]
        actual = glob.iglob(path.join(self.dir, '**'), recursive=True)
        self.assertIsInstance(actual, types.GeneratorType)
        self.assertCountEqual(expected, list(actual))

    def tearDown(self):
        shutil.rmtree(self.dir)


if __name__ == '__main__':
    unittest.main()
