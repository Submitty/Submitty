"""Migration for the Submitty system."""

import os


modules = [
    "cli",
    "fpm",
    "curl",
    "pgsql",
    "zip",
    "mbstring",
    "xml",
    "ds",
    "imagick",
    "intl",
]


def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    os.system("apt-get remove -qqy php*")
    os.system("add-apt-repository -y ppa:ondrej/php")
    os.system("apt-get update")
    for module in modules:
        os.system(f"apt-get install -qqy php8.1-{module}")
    pass


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    os.system("apt-get remove -qqy php8.1-*")
    os.system("add-apt-repository -r -y ppa:ondrej/php")
    os.system("apt-get update")
    for module in modules:
        os.system(f"apt-get install -qqy php-{module}")
    pass
