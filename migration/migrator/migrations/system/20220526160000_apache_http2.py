"""Migration for the Submitty system."""

import subprocess


def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    phpver = subprocess.run(["php", "-r", "print PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;"],
                            stdout=subprocess.PIPE, universal_newlines=True,
                            encoding="utf-8").stdout
    subprocess.run(["a2dismod", f"php{phpver}", "mpm_prefork"])
    subprocess.run(["a2enmod", "mpm_event", "http2"])


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    phpver = subprocess.run(["php", "-r", "print PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;"],
                            stdout=subprocess.PIPE, universal_newlines=True,
                            encoding="utf-8").stdout
    subprocess.run(["a2dismod", "mpm_event", "http2"])
    subprocess.run(["a2enmod", f"php{phpver}", "mpm_prefork"])
