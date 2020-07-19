import shutil
import tempfile
import unittest
import migrator.db


class TestDb(unittest.TestCase):
    def test_no_driver(self):
        with self.assertRaises(RuntimeError):
            migrator.db.Database({}, 'system')

    def test_invalid_driver(self):
        with self.assertRaises(RuntimeError):
            migrator.db.Database({'database_driver': 'mysql'}, 'system')

    def test_db(self):
        db = migrator.db.Database({'database_driver': 'sqlite'}, 'master')
        db.execute("""
            CREATE TABLE users (
                id INTEGER NOT NULL, name VARCHAR,
                fullname VARCHAR,
                password VARCHAR,
                PRIMARY KEY (id)
            )""")
        db.commit()
        self.assertTrue(db.has_table('users'))
        self.assertFalse(db.has_table('fake'))
        self.assertTrue(db.table_has_column('users', 'id'))
        self.assertFalse(db.table_has_column('users', 'fake'))
        db.close()

    def test_get_migration_table(self):
        table = migrator.db.get_migration_table('system', object)
        self.assertEqual('migrations_system', table.__tablename__)

    def test_get_connection_string_sqlite(self):
        params = {
            'database_driver': 'sqlite'
        }
        string = migrator.db.Database.get_connection_string(params)
        self.assertEqual('sqlite://', string)

    def test_get_connection_string_postgresql(self):
        params = {
            'database_driver': 'psql',
            'database_host': 'localhost',
            'database_port': 15432,
            'database_user': 'user',
            'database_password': 'password',
            'dbname': 'test'
        }
        string = migrator.db.Database.get_connection_string(params)
        self.assertEqual('postgresql+psycopg2://user:password@localhost:15432/test', string)

    def test_get_connection_string_postgresql_no_port(self):
        params = {
            'database_driver': 'psql',
            'database_host': 'localhost',
            'database_user': 'user',
            'database_password': 'password',
            'dbname': 'test'
        }
        string = migrator.db.Database.get_connection_string(params)
        self.assertEqual('postgresql+psycopg2://user:password@localhost:5432/test', string)

    def test_get_connection_string_postgresql_path_host(self):
        try:
            host = tempfile.mkdtemp()
            params = {
                'database_driver': 'psql',
                'database_host': host,
                'database_user': 'user',
                'database_password': 'password',
                'dbname': 'test'
            }
            string = migrator.db.Database.get_connection_string(params)
            self.assertEqual(
                'postgresql+psycopg2://user:password@/test?host={}'.format(host),
                string
            )
        finally:
            shutil.rmtree(host)

    def test_invalid_connection_string(self):
        with self.assertRaises(RuntimeError) as cm:
            migrator.db.Database.get_connection_string({'database_driver': 'invalid'})
        self.assertEqual('Invalid driver: invalid', str(cm.exception))
