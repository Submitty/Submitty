"""Migration for the Submitty system."""
import os

def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    with open('/root/.bashrc', 'r') as f:
        lines = f.readlines()

    num = len(lines)
    for i, line in enumerate(lines):
        if line.startswith('alias '):
            num = i + 1

    lines.insert(num, "alias refresh_vagrant_workers='python3 /usr/local/submitty/GIT_CHECKOUT/Submitty/.setup/bin/refresh_vagrant_workers.py'\n")
    with open('/root/.bashrc', 'w') as f:
        f.writelines(lines)

def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
