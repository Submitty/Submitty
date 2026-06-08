from pathlib import Path
import re
from sqlalchemy import text


def up(config, database):
    sql_file = Path(Path(__file__).parent.parent.parent, 'data', 'submitty_db.sql')
    with sql_file.open() as open_file:
        sql = re.sub(r"\\(un)?restrict [^\n]*\n?", '', open_file.read())
        database.execute(text(sql))


def down(config, database):
    database.execute("""
DROP SCHEMA public CASCADE;
CREATE SCHEMA public;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO public;
""")