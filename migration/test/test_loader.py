from pathlib import Path
import unittest

import tempfile
import shutil

from migrator import loader


class TestLoader(unittest.TestCase):
    def setUp(self):
        """Set up the temp directory to use for the tests."""
        self.dir = tempfile.mkdtemp()

    def tearDown(self):
        """Remove the temp directory we used for the tests."""
        shutil.rmtree(self.dir)

    def create_migration(self, name):
        filename = Path(self.dir, name)
        with filename.open('w') as open_file:
            open_file.write("""
def foo():
    return True
""")

    def test_load_module(self):
        self.create_migration('1_test.py')
        module = loader.load_module('1_test', Path(self.dir, '1_test.py'))
        self.assertTrue(module.foo())

    def test_load_migrations(self):
        self.create_migration('1_test.py')
        migrations = loader.load_migrations(Path(self.dir))
        self.assertEqual(1, len(migrations.keys()))
        self.assertListEqual(['1_test'], list(migrations.keys()))
        expected = {
            'id': '1_test',
            'commit_time': None,
            'status': 0,
            'module': loader.load_module('1_test', Path(self.dir, '1_test.py')),
            'table': None
        }
        self.assertDictEqual(expected, migrations['1_test'])
