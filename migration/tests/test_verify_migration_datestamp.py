"""Tests for migration datestamp verification."""

import os
import sys
import unittest
from datetime import datetime, timezone
from pathlib import Path
from unittest.mock import patch, MagicMock

# Add parent directory to path to import the script
script_path = Path(__file__).parent.parent.parent / '.github' / 'scripts'
sys.path.insert(0, str(script_path))

import verify_migration_datestamp  # noqa: E402


class TestParseMigrationFilename(unittest.TestCase):

    def test_valid_migration_filename(self):
        result = verify_migration_datestamp.parse_migration_filename(
            "20240225143000_add_feature.py"
        )
        self.assertIsNotNone(result)
        self.assertEqual(result.year, 2024)
        self.assertEqual(result.month, 2)
        self.assertEqual(result.day, 25)

    def test_invalid_migration_filename(self):
        result = verify_migration_datestamp.parse_migration_filename(
            "__init__.py"
        )
        self.assertIsNone(result)

    def test_invalid_datestamp_format(self):
        result = verify_migration_datestamp.parse_migration_filename(
            "20241325143000_invalid_month.py"
        )
        self.assertIsNone(result)

    def test_wrong_pattern(self):
        result = verify_migration_datestamp.parse_migration_filename(
            "base_migration_course.py"
        )
        self.assertIsNone(result)


class TestGetChangedMigrationFiles(unittest.TestCase):

    @patch('verify_migration_datestamp.subprocess.run')
    def test_get_changed_files_in_pr(self, mock_run):
        mock_result = MagicMock()
        mock_result.stdout = (
            "migration/migrator/migrations/course/20240225143000_new.py\n"
            "migration/migrator/migrations/system/20240101120000_old.py\n"
            "site/app/controllers/SomeController.php"
        )
        mock_run.return_value = mock_result

        with patch.dict(os.environ, {'GITHUB_BASE_REF': 'main'}):
            files = verify_migration_datestamp.get_changed_migration_files()

        self.assertEqual(len(files), 2)
        self.assertIn('20240225143000_new.py', files[0][1])
        self.assertIn('20240101120000_old.py', files[1][1])

    @patch('verify_migration_datestamp.subprocess.run')
    def test_filters_non_migration_files(self, mock_run):
        mock_result = MagicMock()
        mock_result.stdout = (
            "migration/migrator/migrations/course/__init__.py\n"
            "site/app/models/User.php\n"
            "README.md"
        )
        mock_run.return_value = mock_result

        files = verify_migration_datestamp.get_changed_migration_files()

        self.assertEqual(len(files), 0)


class TestVerifyMigrationFreshness(unittest.TestCase):

    @patch('verify_migration_datestamp.get_changed_migration_files')
    def test_no_migrations_returns_true(self, mock_get_files):
        mock_get_files.return_value = []

        result = verify_migration_datestamp.verify_migration_freshness()

        self.assertTrue(result)

    @patch('verify_migration_datestamp.get_changed_migration_files')
    @patch('verify_migration_datestamp.get_current_time')
    def test_fresh_migration_passes(self, mock_time, mock_get_files):
        now = datetime(2024, 2, 25, 14, 30, 0, tzinfo=timezone.utc)
        mock_time.return_value = now

        # Migration is 5 days old (fresh)
        mock_get_files.return_value = [
            ('migration/migrator/migrations/course/20240220143000_new.py',
             '20240220143000_new.py')
        ]

        result = verify_migration_datestamp.verify_migration_freshness()

        self.assertTrue(result)

    @patch('verify_migration_datestamp.get_changed_migration_files')
    @patch('verify_migration_datestamp.get_current_time')
    def test_stale_migration_fails(self, mock_time, mock_get_files):
        """Stale migration older than 7 days should fail."""
        now = datetime(2024, 2, 25, 14, 30, 0, tzinfo=timezone.utc)
        mock_time.return_value = now

        # Migration is 55 days old (stale)
        mock_get_files.return_value = [
            ('migration/migrator/migrations/course/20240101120000_old.py',
             '20240101120000_old.py')
        ]

        result = verify_migration_datestamp.verify_migration_freshness()

        self.assertFalse(result)

    @patch('verify_migration_datestamp.get_changed_migration_files')
    def test_invalid_format_fails(self, mock_get_files):
        mock_get_files.return_value = [
            ('migration/migrator/migrations/course/invalid_migration.py',
             'invalid_migration.py')
        ]

        result = verify_migration_datestamp.verify_migration_freshness()

        self.assertFalse(result)

    @patch('verify_migration_datestamp.MAX_AGE_DAYS', 14)
    @patch('verify_migration_datestamp.get_changed_migration_files')
    @patch('verify_migration_datestamp.get_current_time')
    def test_custom_max_age_14_days(self, mock_time, mock_get_files):
        """Test with custom MAX_AGE_DAYS of 14 days."""
        now = datetime(2024, 2, 25, 14, 30, 0, tzinfo=timezone.utc)
        mock_time.return_value = now

        # Migration is 10 days old (within 14 day limit, should pass)
        mock_get_files.return_value = [
            ('migration/migrator/migrations/course/20240215143000_ten_days.py',
             '20240215143000_ten_days.py')
        ]

        result = verify_migration_datestamp.verify_migration_freshness()
        self.assertTrue(result)


if __name__ == '__main__':
    unittest.main()
