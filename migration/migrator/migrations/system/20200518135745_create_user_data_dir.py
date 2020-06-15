import os
import shutil

"""Migration for the Submitty system."""


def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """

    user_data_path = os.path.join(config.submitty['submitty_data_dir'], 'user_data')
    user = config.submitty_users['php_user']
    script_path = os.path.join(config.submitty['submitty_repository'], '.setup', 'bin', 'setup_sample_user_data.py')

    # Generate user_data directory
    if not os.path.isdir(user_data_path):
        os.system('mkdir ' + user_data_path)
        os.system('chmod 770 ' + user_data_path)
        shutil.chown(user_data_path, user, user)

    # Execute script to populate user_data directory with sample images
    os.system('python3 ' + script_path)

    # Add image magick
    os.system('apt install imagemagick php-imagick -y')


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
