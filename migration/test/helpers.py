"""Module containing some helper functions."""
from pathlib import Path


def create_migration(database, dir, environment, name, status=1, create_file=True):
    if create_file:
        with Path(dir, environment, name).open('w') as open_file:
            open_file.write("""
from pathlib import Path

def up(*_):
    Path("{0}", "{1}.up.txt").open("w").close()

def down(*_):
    Path("{0}", "{1}.down.txt").open("w").close()
""".format(str(dir), name))

    if database is not None:
        database.session.add(
            database.migration_table(id=name.replace('.py', ''), status=status)
        )
        database.session.commit()
