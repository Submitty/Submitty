"""
Migration for the Submitty system.
adds submitty admin json
"""
from pathlib import Path
import shutil
import json
import os

def up(config):
    submitty_admin_filename = str(Path(config.submitty['submitty_install_dir'], 'config', 'submitty_admin.json'))
    if not os.path.isfile(submitty_admin_filename):
        submitty_admin_json = {
            'submitty_admin_username': '',
            'submitty_admin_password': ''
        }
        with open(submitty_admin_filename,'w') as open_file:
            json.dump(submitty_admin_json, open_file, indent=2)
        shutil.chown(submitty_admin_filename, 'root', 'submitty_daemon')
        os.chmod(submitty_admin_filename, 0o440)


def down(config):
    pass
