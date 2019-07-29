#!/usr/bin/env python3

"""Obtain an api auth token for submitty-admin user during installation."""

import os
import json
import subprocess

# Get path to current file directory
current_dir = os.path.dirname(__file__)

# Collect other path information from configuration file
config_file = os.path.join(current_dir, '..', '..', 'config', 'submitty.json')
admin_file = os.path.join(current_dir, '..', '..', 'config', 'submitty_admin.json')

if not os.path.exists(config_file):
    raise Exception('Unable to locate submitty.json configuration file')

with open(config_file, 'r') as file:
    data = json.load(file)
    install_dir = data['submitty_install_dir']
    host_name = data['submission_url']

# Verify submitty_admin file exists
if not os.path.exists(admin_file):
    raise Exception('Unable to locate submitty_admin.json credentials file')

# Load credentials out of admin file
with open(admin_file, 'r') as file:
    creds = json.load(file)

# Construct request list
request = [
    'curl',
    '-d',
    'user_id={}&password={}'.format(creds['submitty_admin_username'],
                                    creds['submitty_admin_password']),
    '-X',
    'POST',
    '{}/api/token'.format(host_name)
]

# Using the credentials call the API to obtain an auth token
print('Obtaining auth token', flush=True)
response = subprocess.run(request, stdout=subprocess.PIPE)

# Check the return code of the 'curl' execution
if response.returncode != 0:
    raise Exception('Failure during curl server call to obtain auth token')

# Turn the response into a json
response_json = json.loads(response.stdout)

# Setup token string
if response_json['status'] != 'success':

    print('Failed to obtain an auth token.', flush=True)
    print('Ask your sysadmin to confirm that ' + admin_file +
          ' contains valid credentials', flush=True)

    token = ''

else:

    token = response_json['data']['token']

# Add token string to submitty_admin json
creds['token'] = token

# Write back to file
with open(admin_file, 'w') as file:
    json.dump(creds, file, indent=4)
