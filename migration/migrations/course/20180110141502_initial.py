from pathlib import Path


def up(conn):
    with conn.cursor() as cursor:
        with open(Path(__file__).parent.parent.parent / 'data' / 'course_tables.sql') as open_file:
            cursor.execute(open_file.read())


def down(conn):
    with conn.cursor() as cursor:
        cursor.execute("""
DROP SCHEMA public CASCADE;
CREATE SCHEMA public;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO public;
""")
