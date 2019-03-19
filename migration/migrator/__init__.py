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


def get_environments():
    """Return the set environments."""
    return ENVIRONMENTS
