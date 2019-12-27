import unittest
import migrator


class TestInit(unittest.TestCase):
    def test_get_all_environments(self):
        expected = ['master', 'system', 'course']
        self.assertListEqual(expected, migrator.ENVIRONMENTS)
        self.assertListEqual(expected, migrator.get_all_environments())
        self.assertEqual(migrator.ENVIRONMENTS, migrator.get_all_environments())

    def test_get_environments_empty(self):
        self.assertEqual([], migrator.get_environments([]))

    def test_get_environments_all(self):
        self.assertEqual(
            ['master', 'system', 'course'],
            migrator.get_environments(['course', 'master', 'system'])
        )

    def test_get_paths(self):
        self.assertEqual(migrator.DIR_PATH, migrator.get_dir_path())
        self.assertEqual(migrator.MIGRATIONS_PATH, migrator.get_migrations_path())

        old_dir = migrator.DIR_PATH
        migrator.DIR_PATH = 'test1'
        old_migration = migrator.MIGRATIONS_PATH
        migrator.MIGRATIONS_PATH = 'test2'

        try:
            self.assertEqual('test1', migrator.get_dir_path())
            self.assertEqual('test2', migrator.get_migrations_path())
        finally:
            migrator.DIR_PATH = old_dir
            migrator.MIGRATIONS_PATH = old_migration
