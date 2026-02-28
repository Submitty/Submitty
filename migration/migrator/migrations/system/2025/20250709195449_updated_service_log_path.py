"""Migration for the Submitty system."""
import os

def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    if not os.path.exists("/var/local/submitty/logs/services"):
        os.makedirs("/var/local/submitty/logs/services", exist_ok=True)


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
