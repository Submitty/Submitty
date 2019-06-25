"""Module to handle loading of migrations and modules."""
from collections import OrderedDict
from importlib.machinery import SourceFileLoader
import re


def load_module(name, path):
    """
    Load the migration file as a python module.

    :param name: Name of module
    :type name: str
    :param path: Path to module
    :type path: pathlib.Path
    """
    # TODO: change this to not use deprecated loader.load_module
    # after dropping Python 3.4 support
    loader = SourceFileLoader(name, str(path))
    module = loader.load_module(name)
    return module


def load_migrations(path):
    """
    Given a path, load all migrations in that path.

    :param path: path to look for migrations in
    :type path: pathlib.Path or str
    """
    migrations = OrderedDict()
    r = re.compile(r'^[0-9]+\_.+\.py$')
    filtered = filter(
        lambda x: r.search(x) is not None,
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
