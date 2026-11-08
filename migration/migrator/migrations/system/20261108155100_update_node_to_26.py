"""Migration for the Submitty system."""

import os


def install_node(major):
    """
    Replace the installed Node.js with the given major version from NodeSource.

    Node.js is purged first because apt-get will not downgrade it on rollback.

    :param major: Node.js major version to install
    :type major: int
    """
    keyring = "/etc/apt/keyrings/nodesource.gpg"
    sources = "/etc/apt/sources.list.d/nodesource.list"
    key_url = "https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key"
    repo_url = f"https://deb.nodesource.com/node_{major}.x"

    os.system("npm -g uninstall npm")
    os.system("apt-get purge nodejs -y")
    os.system(f"rm -f {sources} {keyring}")
    os.system(f"curl -fsSL {key_url} | gpg --batch --dearmor -o {keyring}")
    os.system(f"chmod o+r {keyring}")
    os.system(f"echo 'deb [signed-by={keyring}] {repo_url} nodistro main' > {sources}")
    os.system("apt-get update")
    os.system("apt-get install nodejs -y")


def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    if not config.submitty["worker"]:
        install_node(26)


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    if not config.submitty["worker"]:
        install_node(20)
