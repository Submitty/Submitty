from pathlib import Path
from sqlalchemy import text


def up(config, database):
    sql_file = Path(Path(__file__).parent.parent.parent, 'data', 'submitty_db.sql')
    with sql_file.open() as open_file:
        database.session.execute(text(open_file.read()))
        database.session.commit()


def down(config, database):
    database.execute("""
DROP SCHEMA public CASCADE;
CREATE SCHEMA public;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO public;
""")
