"""Migration for the Submitty system."""
import subprocess


def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    subprocess.check_call(
        ('a2enmod', 'proxy_http', 'proxy_wstunnel'),
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE
    )

    subprocess.check_call(
        ('systemctl', 'restart', 'apache2'),
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE
    )
