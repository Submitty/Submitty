"""Migration for the Submitty system."""
import subprocess


def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """

    ps = subprocess.Popen(
        ('curl', '-sL', 'https://deb.nodesource.com/setup_10.x'),
        stdout=subprocess.PIPE
    )
    subprocess.check_call(
        ('bash', '-'),
        stdin=ps.stdout,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE
    )
    ps.wait()
    subprocess.check_call(
        ('apt-get', 'install', '-y', 'nodejs'),
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE
    )


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
