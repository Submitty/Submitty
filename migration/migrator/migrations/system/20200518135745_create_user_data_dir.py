import os
import shutil
import stat

"""Migration for the Submitty system."""


def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """

    user_data_path = os.path.join(config.submitty['submitty_data_dir'], 'user_data')
    user = config.submitty_users['php_user']

    # Generate user_data directory
    if not os.path.isdir(user_data_path):
        os.mkdir(user_data_path)
        os.chmod(user_data_path, stat.S_IRWXU | stat.S_IRWXG)
        shutil.chown(user_data_path, user, user)

    # Add image magick
    os.system('apt install imagemagick php-imagick -y')


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
