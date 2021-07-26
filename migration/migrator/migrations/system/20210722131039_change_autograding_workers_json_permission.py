"""Migration for the Submitty system."""

from pathlib import Path
import os

def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    workers_json = str(Path(config.submitty['submitty_install_dir'], 'config', 'autograding_workers.json'))
    os.chmod(workers_json, 0o660)


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    workers_json = str(Path(config.submitty['submitty_install_dir'], 'config', 'autograding_workers.json'))
    os.chmod(workers_json, 0o460)
