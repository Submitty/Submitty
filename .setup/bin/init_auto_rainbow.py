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

# Write back to submitty_admin json file
with open(admin_file, 'w') as file:
    json.dump(creds, file, indent=4)

# Configure path to where status file should be saved
# This file is saved somewhere submitty_php can read from so the PHP side of
# submitty is able to determine if it can use certain features
status_file = os.path.join(install_dir, 'site', 'config', 'submitty_admin_status.json')

does_exist = True if token else False

# Write to status file
with open(status_file, 'w') as file:
    json.dump({
        'submitty_admin_exists': does_exist,
        'submitty_admin_username': creds['submitty_admin_username']
    }, file, indent=4)

cmd_output = os.popen('chmod 0440 ' + status_file).read()
cmd_output = os.popen('chown submitty_php:submitty_php ' + status_file).read()
