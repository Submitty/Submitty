#!/usr/bin/env python3

"""
Obtain an api auth token for submitty-admin user during installation.

Also write a file into the file system which can be used by PHP to determine if
submitty-admin is present at the system level and correctly configured.
"""

import os
import json
import subprocess

# Get path to current file directory
current_dir = os.path.dirname(__file__)

# Collect other path information from configuration file
config_file = os.path.join(current_dir, '..', '..', 'config', 'submitty.json')
submitty_admin_file = os.path.join(current_dir, '..', '..', 'config',
                                   'submitty_admin.json')
submitty_users_file = os.path.join(current_dir, '..', '..', 'config',
                                   'submitty_users.json')


# ========================================================================
def save_verified_submitty_admin_user(verified_user):
    '''
    The status of the submitty_admin token must be saved submitty_php
    can read from so the PHP side of submitty is able to determine if it
    can use certain features.
    '''

    # Verify submitty_admin file exists
    if not os.path.exists(submitty_users_file):
        raise Exception('Unable to locate '+submitty_users_file)

    # Load submitty users file
    with open(submitty_users_file, 'r') as f:
        users_json = json.load(f)

    users_json['verified_submitty_admin_user'] = verified_user

    # Write back to submitty_users json file
    with open(submitty_users_file, 'w') as f:
        json.dump(users_json, f, indent=4)


# ========================================================================

if not os.path.exists(config_file):
    save_verified_submitty_admin_user("")
    raise Exception('Unable to locate submitty.json configuration file')

with open(config_file, 'r') as f:
    data = json.load(f)
    install_dir = data['submitty_install_dir']
    host_name = data['submission_url']

# Verify submitty_admin file exists
if not os.path.exists(submitty_admin_file):
    save_verified_submitty_admin_user("")
    raise Exception('Unable to locate submitty_admin.json credentials file')

# Load credentials out of admin file
with open(submitty_admin_file, 'r') as f:
    creds = json.load(f)

response = subprocess.run([os.path.join(current_dir, '..', '..', 'sbin', 'api_token_generate.php'), creds['submitty_admin_username']], stdout=subprocess.PIPE, stderr=subprocess.PIPE, encoding='utf-8')

if response.returncode != 0:
    print("Failure to get token for submitty admin account")
    exit(0)

token = response.stdout

# Add token string to submitty_admin json
creds['token'] = token

# Write back to submitty_admin json file
with open(submitty_admin_file, 'w') as f:
    json.dump(creds, f, indent=4)

if (token):
    verified_user = creds['submitty_admin_username']
else:
    verified_user = ""

save_verified_submitty_admin_user(verified_user)
