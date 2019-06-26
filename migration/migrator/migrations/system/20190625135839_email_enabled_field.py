"""
Migration for the Submitty system.
adds email_enabled boolean to config/email.json
"""
from pathlib import Path
import json
import os


def up(config):
    email_filename = str(Path(config.submitty['submitty_install_dir'], 'config', 'email.json'))
    # read json and add email_enabled field
    with open(email_filename,'r') as open_file:
        email_json = json.load(open_file)
        email_json['email_enabled'] = True
    # remove file
    os.remove(email_filename) 
    # write file again with new json
    with open(email_filename, 'w') as open_file:
        json.dump(email_json, open_file, indent=2)

# no need for down as email_enabled is not used in previous builds
def down(config):
    pass
