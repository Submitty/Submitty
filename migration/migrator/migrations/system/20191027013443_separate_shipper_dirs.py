import os
import shutil

IN_PROGRESS_PATH = '/var/local/submitty/grading'


def up(config):
    if not os.path.exists(IN_PROGRESS_PATH):
        os.mkdir(IN_PROGRESS_PATH)
    shutil.chown(IN_PROGRESS_PATH, 'submitty_daemon', 'submitty_daemonphp')
    os.chmod(IN_PROGRESS_PATH, 0o770)


def down(config):
    pass
