import os
import shutil


def up(config):
    IN_PROGRESS_PATH = os.path.join(config.submitty['submitty_data_dir'], 'grading')

    os.makedirs(IN_PROGRESS_PATH, exist_ok=True)
    shutil.chown(IN_PROGRESS_PATH, 'submitty_daemon', 'submitty_daemonphp')
    os.chmod(IN_PROGRESS_PATH, 0o770)


def down(config):
    pass
