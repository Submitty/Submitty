from unittest import TestCase
from urllib.parse import unquote, urlsplit

from submitty_utils import db_utils


class TestDbUtils(TestCase):
    def test_connection_string(self):
        self.assertEqual(
            "postgresql://test:my_pass@127.0.0.1:1111/db_name",
            db_utils.generate_connect_string(
                "127.0.0.1",
                1111,
                "db_name",
                "test",
                "my_pass",
            ),
        )

    def test_connection_string_dir(self):
        self.assertEqual(
            "postgresql://test:my_pass@/db_name?host=/var/run/postgresql",
            db_utils.generate_connect_string(
                "/var/run/postgresql",
                5432,
                "db_name",
                "test",
                "my_pass",
            ),
        )

    def test_connection_string_escapes_user_and_password(self):
        self.assertEqual(
            "postgresql://test%40host:p%40ss%3Aw%2Fo%25rd@127.0.0.1:1111/db_name",
            db_utils.generate_connect_string(
                "127.0.0.1",
                1111,
                "db_name",
                "test@host",
                "p@ss:w/o%rd",
            ),
        )

    def test_connection_string_password_round_trip(self):
        for host in ("localhost", "/var/run/postgresql"):
            with self.subTest(host=host):
                parts = urlsplit(db_utils.generate_connect_string(
                    host,
                    5432,
                    "submitty",
                    "submitty_dbuser",
                    "se@cret%40x",
                ))
                self.assertEqual("submitty_dbuser", unquote(parts.username))
                self.assertEqual("se@cret%40x", unquote(parts.password))
                self.assertEqual("/submitty", parts.path)
