"""Migration for the Submitty system."""
import os

def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    # Install and enable mod_qos
    os.system('apt-get install -y libapache2-mod-qos')
    os.system('a2enmod qos')

    # Restart Apache to apply changes
    os.system('systemctl restart apache2')


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
