import os
from pathlib import Path

"""Migration for the Submitty system."""


def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    os.chmod(str(Path(config.submitty['submitty_install_dir'], 'config', 'autograding_containers.json')), 0o660)


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
