"""Migration for the Submitty system."""


import os
import subprocess


def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    subprocess.check_call(
        ["apt-get", "install", "-qqy", "python3-venv"],
        env=os.environ.copy()
    )


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
