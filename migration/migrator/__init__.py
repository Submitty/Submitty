"""The migrator python module."""

from pathlib import Path


VERSION = "2.0.0"
DIR_PATH = Path(__file__).parent.resolve()
MIGRATIONS_PATH = DIR_PATH / 'migrations'
ENVIRONMENTS = ['master', 'system', 'course']


def get_dir_path():
    """Return the set directory path."""
    return DIR_PATH


def get_migrations_path():
    """Return the set migration path."""
    return MIGRATIONS_PATH


def get_all_environments():
    """Return the set environments."""
    return ENVIRONMENTS


def get_environments(candidates=None):
    # make sure the order is of 'master', 'system', 'course' depending on what
    # environments have been selected system must be run after master initially
    # as system relies on master DB being setup for migration table
    if candidates is None:
        return []
    candidates = [str(candidate).lower() for candidate in candidates]
    environments = []
    for env in get_all_environments():
        if env in candidates:
            environments.append(env)
    return environments
