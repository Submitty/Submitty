from unittest import TestCase

from submitty_utils import db_utils


class TestDbUtils(TestCase):
    def test_connection_string(self):
        self.assertEqual(
            'postgresql://test:my_pass@127.0.0.1:1111/db_name',
            db_utils.generate_connect_string(
                '127.0.0.1',
                1111,
                'db_name',
                'test',
                'my_pass',
            ))

    def test_connection_string_dir(self):
        self.assertEqual(
            'postgresql://test:my_pass@/db_name?host=/var/run/postgresql',
            db_utils.generate_connect_string(
                '/var/run/postgresql',
                5432,
                'db_name',
                'test',
                'my_pass',
            ))
