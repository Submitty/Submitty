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


if __name__ == '__main__':
    unittest.main()
