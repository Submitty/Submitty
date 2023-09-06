"""Migration for the Submitty system."""
import os

def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    os.system("npm -g uninstall npm")
    os.system("apt-get purge nodejs -y")
    os.system("rm -r /etc/apt/sources.list.d/nodesource.list")
    os.system("rm -r /etc/apt/keyrings/nodesource.gpg")
    os.system("curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | sudo gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg")
    os.system("chmod o+r /etc/apt/keyrings/nodesource.gpg")
    os.system("echo 'deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_16.x nodistro main' > /etc/apt/sources.list.d/nodesource.list")
    os.system("apt-get update")
    os.system("apt-get install nodejs -y")


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
