"""Module to handle loading of migrations and modules."""
from collections import OrderedDict
from importlib.machinery import SourceFileLoader


def load_module(name, path):
    """Load the migration file as a python module."""
    # TODO: change this to not use deprecated loader.load_module
    # after dropping Python 3.4 support
    loader = SourceFileLoader(name, str(path))
    module = loader.load_module(name)
    return module


def load_migrations(path):
    """Given a path, load all migrations in that path."""
    migrations = OrderedDict()
    filtered = filter(
        lambda x: x.endswith('.py'),
        [x.name for x in path.iterdir()]
    )
    for migration in sorted(filtered):
        migration_id = migration[:-3]
        migrations[migration_id] = {
            'id': migration_id,
            'commit_time': None,
            'status': 0,
            'module': load_module(migration_id, path / migration),
            'table': None
        }
    return migrations
