from pathlib import Path
import re
from sqlalchemy import text


def up(config, database, semester, course):
    sql_file = Path(Path(__file__).parent.parent.parent, 'data', 'course_tables.sql')
    with sql_file.open() as open_file:
        sql = re.sub(r"\\(un)?restrict [^\n]*\n?", '', open_file.read())
        database.execute(text(sql))


def down(config, database, semester, course):
    database.execute("""
DROP SCHEMA public CASCADE;
CREATE SCHEMA public;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO public;
""")