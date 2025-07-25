#!/usr/bin/env python3

"""
Automatically generate rainbow grades. The source should be either "submitty_gui"
or "submitty_daemon". If the source is "submitty_gui", then the script will only
generate grade summaries for the course. If the source is "submitty_daemon", then the
script will submit the build process for courses using GUI customization, constructing
the most up-to-date customization file prior to generating grade summaries.

usage: python3 auto_rainbow_grades.py <semester> <course> <source>
"""

# Imports
import sys
import os
import subprocess
import shutil
import pwd
import json
import datetime
from pathlib import Path
import getpass

# Verify correct number of command line arguments
if len(sys.argv) != 4:
    raise Exception('You must pass 3 command line arguments - semester, course, and source')

# Get path to current file directory
current_dir = os.path.dirname(__file__)

# Collect other path information from configuration file
config_file = os.path.join(current_dir, '..', 'config', 'submitty.json')

if not os.path.exists(config_file):
    raise Exception('Unable to locate submitty.json configuration file')

with open(config_file, 'r') as file:
    data = json.load(file)
    install_dir = data['submitty_install_dir']
    data_dir = data['submitty_data_dir']

# Collect user information from configuration file
users_config_file = os.path.join(install_dir, 'config', 'submitty_users.json')

if not os.path.exists(users_config_file):
    raise Exception('Unable to locate submitty_users.json configuration file')

with open(users_config_file, 'r') as file:
    data = json.load(file)
    daemon_user = data['daemon_user']


# Confirm that submitty_daemon user is running this script
if data['daemon_user'] != getpass.getuser():
    raise Exception('ERROR: This script must be run by the submitty_daemon user')


# Configure variables
semester = sys.argv[1]
course = sys.argv[2]
source = sys.argv[3]

if source not in ["submitty_gui", "submitty_daemon"]:
    raise Exception('ERROR: Source must be either "submitty_gui" or "submitty_daemon"')

user = daemon_user
rainbow_grades_path = os.path.join(install_dir, 'GIT_CHECKOUT', 'RainbowGrades')
courses_path = os.path.join(data_dir, 'courses')


def log_message(message):
    """Global log message about rainbow grades."""
    today = datetime.datetime.now()
    log_file_path = Path(data_dir, 'logs', 'rainbow_grades',
                         "{:04d}{:02d}{:02d}.txt".format(today.year,
                                                         today.month,
                                                         today.day))
    # append to the file
    with open(log_file_path, 'a') as file:
        timestring = today.strftime("%Y-%m-%d %H:%M:%S%z")
        file.write(timestring + " " + message + "\n")


log_message("Start processing " + semester + " " + course)


# Verify user exists
users = pwd.getpwall()

user_found = False

for item in users:
    if user in item:
        user_found = True

if user_found is False:
    raise Exception('Unable to locate the specified user {}'.format(user))

print('Started build at {}'.format(datetime.datetime.now()), flush=True)

# Generate path information
rg_course_path = os.path.join(courses_path, semester, course, 'rainbow_grades')

# Verify that customization.json exist
if os.path.exists(rg_course_path + '/customization.json'):
    pass

else:
    raise Exception('Unable to find a customization file')

# If makefile does not exist then copy and configure one from the main rainbow grades
# repo
if not os.path.exists(rg_course_path + '/Makefile'):

    # Copy Makefile from master rainbow grades directory
    # to course specific directory
    print('Copying initial files', flush=True)
    shutil.copyfile(rainbow_grades_path + '/SAMPLE_Makefile',
                    rg_course_path + '/Makefile')

    # Setup Makefile path
    print('Configuring Makefile', flush=True)
    makefile_path = os.path.join(rg_course_path, 'Makefile')

    # Read in the file
    with open(makefile_path, 'r') as file:
        filedata = file.read()

    # Replace the target strings
    filedata = filedata.replace('username', user)
    filedata = filedata.replace('/<PATH_TO_SUBMITTY_REPO>/RainbowGrades',
                                rainbow_grades_path)
    filedata = filedata.replace('submitty.cs.rpi.edu', 'localhost')
    filedata = filedata.replace('<SEMESTER>/<COURSE>', '{}/{}'.format(semester, course))

    # Write the file out again
    with open(makefile_path, 'w') as file:
        file.write(filedata)

else:

    print('Previously configured Makefile detected', flush=True)


# Change directory to course specific directory
os.chdir(rg_course_path)

# Verify submitty_admin file exists
creds_file = os.path.join(install_dir, 'config', 'submitty_admin.json')

if not os.path.exists(creds_file):
    raise Exception('Unable to locate submitty_admin.json credentials file')

# Load credentials out of admin file
with open(creds_file, 'r') as file:
    creds = json.load(file)

# Take this path if we DID NOT get an auth token
if 'token' not in creds or not creds['token']:

    print('Attempting to continue with previously generated grade summaries',
          flush=True)

    # We may still continue execution if grade summaries had been previously manually
    # generated, Check grade summaries directory to see if it contains any summaries
    reports_path = os.path.join(courses_path, semester, course, 'reports', 'all_grades')
    file_count = sum([len(files) for r, d, files in os.walk(reports_path)])

    if file_count == 0:
        raise Exception('Failure - The grade summaries directory is empty')

# Take this path if we DID get an auth token
else:

    # Construct cmd string
    cmd = [
        '{}/sbin/generate_grade_summaries.py'.format(install_dir),
        semester,
        course,
        source
    ]

    # Call generate_grade_summaries.py script to generate grade summaries for the
    # course
    print('Generating grade summaries', flush=True)
    cmd_return_code = subprocess.call(cmd)

    # Check return code of generate_grade_summaries.py execution
    if cmd_return_code != 0:
        raise Exception('Failure generating grade summaries')

# Run make pull_test (command outputs capture in cmd_output for debugging)
print('Pulling in grade summaries', flush=True)
cmd_output = os.popen('make pull_test').read()

# Run make
print('Compiling rainbow grades', flush=True)
cmd_output = os.popen('make').read()

# Run make push_test
print('Exporting to summary_html', flush=True)
cmd_output = os.popen('make push_test').read()

# Recursively update permissions for all files in the rainbow_grades directory
print('Updating permissions', flush=True)
cmd_output = os.popen('chmod -R --silent o-rwx ' + rg_course_path).read()

summary_html_path = os.path.join(courses_path,
                                 semester,
                                 course,
                                 'reports',
                                 'summary_html')
cmd_output = os.popen('chmod -R --silent o-rwx ' + summary_html_path).read()

print('Done', flush=True)

log_message("Finished         " + semester + " " + course)
