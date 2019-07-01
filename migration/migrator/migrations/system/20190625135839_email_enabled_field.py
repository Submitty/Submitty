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
    try:
        with open(email_filename,'r') as open_file:
            email_json = json.load(open_file)
            email_json['email_enabled'] = True
    except FileNotFoundError:
        email_json = {
            'email_enabled': True,
            'email_user': '',
            'email_password': '',
            'email_sender': 'submitty@myuniversity.edu',
            'email_reply_to': 'submitty_do_not_reply@myuniversity.edu',
            'email_server_hostname': 'mail.myuniversity.edu',
            'email_server_port': 25
            }

    # write file again with new json
    with open(email_filename, 'w') as open_file:
        json.dump(email_json, open_file, indent=2)

# no need for down as email_enabled is not used in previous builds
def down(config):
    pass
