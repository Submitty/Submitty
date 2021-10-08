import os
import shutil
import stat

"""Migration for the Submitty system."""


def up(config):

    user_data_path = os.path.join(config.submitty['submitty_data_dir'], 'logs', 'daemon_job_queue')
    user = config.submitty_users['daemon_user']

    # Generate user_data directory
    if not os.path.isdir(user_data_path):
        os.mkdir(user_data_path)
        os.chmod(user_data_path, stat.S_IRWXU | stat.S_IRWXG)
        shutil.chown(user_data_path, user, user)
    
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
