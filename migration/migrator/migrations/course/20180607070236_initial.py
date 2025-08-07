from pathlib import Path
from sqlalchemy import text


def up(config, database, semester, course):
    sql_file = Path(Path(__file__).parent.parent.parent, 'data', 'course_tables.sql')
    with sql_file.open() as open_file:
        database.execute(text(open_file.read()))


def down(config, database, semester, course):
    database.execute("""
DROP SCHEMA public CASCADE;
CREATE SCHEMA public;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO public;
""")
