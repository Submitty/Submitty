"""The migrator python module."""

from pathlib import Path


VERSION = "2.1.0"
DIR_PATH = Path(__file__).parent.resolve()
MIGRATIONS_PATH = DIR_PATH / 'migrations'
TRIGGERS_PATH = DIR_PATH / 'triggers'
ENVIRONMENTS = ['master', 'system', 'course']


def get_dir_path():
    """Return the set directory path."""
    return DIR_PATH


def get_migrations_path():
    """Return the set migration path."""
    return MIGRATIONS_PATH


def get_triggers_path():
    """Return the set triggers path."""
    return TRIGGERS_PATH


def get_all_environments():
    """Return the set environments."""
    return ENVIRONMENTS


def get_environments(candidates):
    """
    Return a list of valid environments in proper order from candidates list.

    Given some list of environments, we validate that they're valid environments
    from our list (see ENVIRONMENTS) and that they come in the specific order
    of ENVIRONMENTS, as if that order is not maintained, the system can break
    as at least the initial migration for system rely on the initial migration
    of master.

    :param candidates: List of environments to include
    :type candidates: list
    :return: List of valid environments in allowed order
    :rtype: list
    """
    candidates = [str(candidate).lower() for candidate in candidates]
    environments = []
    for env in get_all_environments():
        if env in candidates:
            environments.append(env)
    return environments
