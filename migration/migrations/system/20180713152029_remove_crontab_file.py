import os
import shutil
from pathlib import Path

def up(config):
    daemon_user = config.submitty_users['daemon_user']
    # just delete the crontab file for the daemon user
    os.system("crontab -r -u "+daemon_user)
    # clean up the old queue files & directory
    to_be_built_dir = Path(config.submitty['submitty_data_dir'], 'to_be_built')
    shutil.rmtree(to_be_built_dir, ignore_errors=True)
    pass

def down(config):
    pass
