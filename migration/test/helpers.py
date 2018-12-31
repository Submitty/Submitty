"""Module containing some helper functions."""
from pathlib import Path


def create_migration(database, dir, environment, name, create_file=True, status=1):
    if create_file:
        with Path(dir, environment, name).open('w') as open_file:
                open_file.write('pass')
    database.session.add(
        database.migration_table(id=name.replace('.py', ''), status=status)
    )
    database.session.commit()
