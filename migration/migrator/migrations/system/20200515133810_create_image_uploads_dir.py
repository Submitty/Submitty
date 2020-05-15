import os
import shutil

"""Migration for the Submitty system."""


def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """

    path = config.submitty['submitty_data_dir'] + '/image_uploads'
    user = config.submitty_users['php_user']

    if not os.path.isdir(path):
        os.system('mkdir ' + path)
        os.system('chmod 770 ' + path)
        shutil.chown(path, user, user)


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
