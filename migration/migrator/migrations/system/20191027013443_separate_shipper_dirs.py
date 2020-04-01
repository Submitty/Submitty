import os
import shutil

IN_PROGRESS_PATH = '/var/local/submitty/grading'


def up(config):
    os.makedirs(IN_PROGRESS_PATH, exist_ok=True)
    shutil.chown(IN_PROGRESS_PATH, 'submitty_daemon', 'submitty_daemonphp')
    os.chmod(IN_PROGRESS_PATH, 0o770)


def down(config):
    pass
