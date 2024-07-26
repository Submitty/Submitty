"""Migration for the Submitty system."""
import os

def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    os.system("alias refresh_vagrant_workers='python3 /usr/local/submitty/GIT_CHECKOUT/Submitty/.setup/bin/refresh_vagrant_workers.py'")

def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
