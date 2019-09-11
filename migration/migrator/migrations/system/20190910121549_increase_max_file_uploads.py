"""Migration for the Submitty system."""
import os
import subprocess

def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    PHP_VERSION = subprocess.check_output("php -r 'print PHP_MAJOR_VERSION.\".\".PHP_MINOR_VERSION;'", shell=True).decode("utf-8")
  
    os.system("sed -i -e 's/^max_file_uploads = 20/max_file_uploads = 100/g' /etc/php/" + PHP_VERSION + "/fpm/php.ini")
    os.system("sudo systemctl restart php" + PHP_VERSION + "-fpm.service")
    os.system("systemctl restart apache2.service")

