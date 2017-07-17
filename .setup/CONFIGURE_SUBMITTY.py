#!/usr/bin/env python3

import argparse
from collections import OrderedDict
import grp
import json
import os
import pwd
import shutil


def get_uid(user):
    return pwd.getpwnam(user).pw_uid


def get_gid(user):
    return pwd.getpwnam(user).pw_gid


def get_ids(user):
    try:
        return get_uid(user), get_gid(user)
    except KeyError:
        raise SystemExit("ERROR: Could not find user: " + user)


def get_input(question, default=""):
    add = "[{}] ".format(default) if default != "" else ""
    user = input("{}: {}".format(question, add)).strip()
    if user == "":
        user = default
    return user


##############################################################################
# this script must be run by root or sudo
if os.getuid() != 0:
    raise SystemExit('ERROR: This script must be run by root or sudo')

parser = argparse.ArgumentParser(description='Submitty configuration script')
parser.add_argument('--debug', action='store_true', default=False, help='Configure Submitty in debug mode')
args = parser.parse_args()

# determine location of SUBMITTY GIT repository
# this script (CONFIGURES_SUBMITTY.py) is in the top level directory of the repository
# (this command works even if we run configure from a different directory)
SETUP_SCRIPT_DIRECTORY = os.path.dirname(os.path.realpath(__file__))
SUBMITTY_REPOSITORY = os.path.dirname(SETUP_SCRIPT_DIRECTORY)

# recommended (default) directory locations
# FIXME: Check that directories exist and are readable/writeable?
SUBMITTY_INSTALL_DIR = '/usr/local/submitty'
SUBMITTY_DATA_DIR = '/var/local/submitty'
SUBMITTY_TUTORIAL_DIR = os.path.join(SUBMITTY_INSTALL_DIR, 'GIT_CHECKOUT_Tutorial')

TAGRADING_LOG_PATH = os.path.join(SUBMITTY_DATA_DIR, 'logs')
AUTOGRADING_LOG_PATH = os.path.join(SUBMITTY_DATA_DIR, 'logs', 'autograding')

##############################################################################

# recommended names for special users & groups related to the SUBMITTY system
HWPHP_USER = 'hwphp'
HWCGI_USER = 'hwcgi'
HWCRON_USER = 'hwcron'
HWPHP_UID, HWPHP_GID = get_ids(HWPHP_USER)
HWCGI_UID, HWCGI_GID = get_ids(HWCGI_USER)
HWCRON_UID, HWCRON_GID = get_ids(HWCRON_USER)

# System Groups
HWCRONPHP_GROUP = 'hwcronphp'
try:
    grp.getgrnam(HWCRONPHP_GROUP)
except KeyError:
    raise SystemExit("ERROR: Could not find group: " + HWCRONPHP_GROUP)

COURSE_BUILDERS_GROUP = 'course_builders'
try:
    grp.getgrnam(COURSE_BUILDERS_GROUP)
except KeyError:
    raise SystemExit("ERROR: Could not find group: " + COURSE_BUILDERS_GROUP)

##############################################################################

# This value must be at least 60: assumed in INSTALL_SUBMITTY.sh generation of crontab
NUM_UNTRUSTED = 60

FIRST_UNTRUSTED_UID, FIRST_UNTRUSTED_GID = get_ids('untrusted00')

# confirm that the uid/gid of the untrusted users are sequential
for i in range(1, NUM_UNTRUSTED):
    untrusted_user = "untrusted{:0=2d}".format(i)
    uid, gid = get_ids(untrusted_user)
    if uid != FIRST_UNTRUSTED_UID + i:
        raise SystemExit('CONFIGURATION ERROR: untrusted UID not sequential: ' + untrusted_user)
    elif gid != FIRST_UNTRUSTED_GID + i:
        raise SystemExit('CONFIGURATION ERROR: untrusted GID not sequential: ' + untrusted_user)

##############################################################################

# adjust this number depending on the # of processors
# available on your hardware
MAX_INSTANCES_OF_GRADE_STUDENTS = 15

# if queue is empty, wait this long before checking the queue again
GRADE_STUDENTS_IDLE_SECONDS = 5

# each grade_students.sh process should idle for this long total
# before terminating the process
GRADE_STUDENTS_IDLE_TOTAL_MINUTES = 16

# how often should the cron job launch a new grade_students.sh script?
# 4 starts per hour  = every 15 minutes
# 12 starts per hour = every 5 minutes
# 15 starts per hour = every 4 minutes
GRADE_STUDENTS_STARTS_PER_HOUR = 20

##############################################################################

CONFIGURATION_FILE = os.path.join(SUBMITTY_INSTALL_DIR, '.setup', 'INSTALL_SUBMITTY.sh')
CONFIGURATION_JSON = os.path.join(SUBMITTY_INSTALL_DIR, '.setup', 'submitty_conf.json')

##############################################################################

defaults = {'database_host': 'localhost',
            'database_user': 'hsdbu',
            'submission_url': '',
            'authentication_method': 1}

if os.path.isfile(CONFIGURATION_JSON):
    with open(CONFIGURATION_JSON) as conf_file:
        defaults = json.load(conf_file)
    defaults['authentication_method'] = 1 if defaults['authentication_method'] == 'PamAuthentication' else 2

print("\nWelcome to the Submitty Homework Submission Server Configuration\n")
DEBUGGING_ENABLED = args.debug is True

if DEBUGGING_ENABLED:
    print('!! DEBUG MODE ENABLED !!')
    print()

print('Hit enter to use default in []')
print()

DATABASE_HOST = get_input('What is the database host?', defaults['database_host'])
print()

DATABASE_USER = get_input('What is the database user?', defaults['database_user'])
print()

default = ''
if 'database_password' in defaults and DATABASE_USER == defaults['database_user']:
    default = '(Leave blank to use same password)'
DATABASE_PASS = get_input('What is the database password for {}? {}'.format(DATABASE_USER, default))
if DATABASE_PASS == '' and DATABASE_USER == defaults['database_user'] and 'database_password' in defaults:
    DATABASE_PASS = defaults['database_password']
print()

SUBMISSION_URL = get_input('What is the url for submission? (ex: http://192.168.56.101 or https://submitty.cs.rpi.edu)', defaults['submission_url']).rstrip('/')
print()

print("What authentication method to use:\n1. PAM\n2. Database\n")
while True:
    try:
        auth = int(get_input('Enter number?', defaults['authentication_method']))
    except ValueError:
        auth = 0
    if 0 < auth < 3:
        break
    print('Number must be between 0 and 3')
print()
print()

if auth == 1:
    AUTHENTICATION_METHOD = 'PamAuthentication'
else:
    AUTHENTICATION_METHOD = 'DatabaseAuthentication'

TAGRADING_URL = SUBMISSION_URL + '/hwgrading'
CGI_URL = SUBMISSION_URL + '/cgi-bin'

##############################################################################
# make the installation setup directory

SETUP_DIR = os.path.join(SUBMITTY_INSTALL_DIR, '.setup')
if os.path.isdir(SETUP_DIR):
    shutil.rmtree(SETUP_DIR)
os.makedirs(SETUP_DIR, exist_ok=True)
shutil.chown(SETUP_DIR, 'root', 'root')
os.chmod(SETUP_DIR, 700)

##############################################################################
# WRITE THE VARIABLES TO A FILE

CONFIGURATION_FILE = os.path.join(SUBMITTY_INSTALL_DIR, '.setup', 'INSTALL_SUBMITTY.sh')
with open(CONFIGURATION_FILE, 'w') as open_file:
    def write(x=''):
        print(x, file=open_file)
    write('#!/bin/bash')
    write()

    write('# Variables prepared by CONFIGURE_SUBMITTY.py')
    write('# Manual editing is allowed (but will be clobbered if CONFIGURE_SUBMITTY.py is re-run)')
    write()

    write('SUBMITTY_INSTALL_DIR=' + SUBMITTY_INSTALL_DIR)
    write('SUBMITTY_REPOSITORY=' + SUBMITTY_REPOSITORY)
    write('SUBMITTY_TUTORIAL_DIR=' + SUBMITTY_TUTORIAL_DIR)
    write('SUBMITTY_DATA_DIR=' + SUBMITTY_DATA_DIR)
    write('HWPHP_USER=' + HWPHP_USER)
    write('HWCGI_USER=' + HWCGI_USER)
    write('HWCRON_USER=' + HWCRON_USER)
    write('HWCRONPHP_GROUP=' + HWCRONPHP_GROUP)
    write('COURSE_BUILDERS_GROUP=' + COURSE_BUILDERS_GROUP)

    write('NUM_UNTRUSTED=' + str(NUM_UNTRUSTED))
    write('FIRST_UNTRUSTED_UID=' + str(FIRST_UNTRUSTED_UID))
    write('FIRST_UNTRUSTED_GID=' + str(FIRST_UNTRUSTED_UID))

    write('HWCRON_UID=' + str(HWCRON_UID))
    write('HWCRON_GID=' + str(HWCRON_GID))
    write('HWPHP_UID=' + str(HWPHP_UID))
    write('HWPHP_GID=' + str(HWPHP_GID))

    write('DATABASE_HOST=' + DATABASE_HOST)
    write('DATABASE_USER=' + DATABASE_USER)
    write('DATABASE_PASSWORD=' + DATABASE_PASS)

    write('AUTHENTICATION_METHOD=' + AUTHENTICATION_METHOD)

    write('SUBMISSION_URL=' + SUBMISSION_URL)
    write('TAGRADING_URL=' + TAGRADING_URL)
    write('CGI_URL=' + CGI_URL)

    write('AUTOGRADING_LOG_PATH=' + AUTOGRADING_LOG_PATH)
    write('SITE_LOG_PATH=' + TAGRADING_LOG_PATH)

    write('MAX_INSTANCES_OF_GRADE_STUDENTS=' + str(MAX_INSTANCES_OF_GRADE_STUDENTS))
    write('GRADE_STUDENTS_IDLE_SECONDS=' + str(GRADE_STUDENTS_IDLE_SECONDS))
    write('GRADE_STUDENTS_IDLE_TOTAL_MINUTES=' + str(GRADE_STUDENTS_IDLE_TOTAL_MINUTES))
    write('GRADE_STUDENTS_STARTS_PER_HOUR=' + str(GRADE_STUDENTS_STARTS_PER_HOUR))
    write()
    write('DEBUGGING_ENABLED=' + ('true' if DEBUGGING_ENABLED else 'false'))
    write()
    write('# Now actually run the installation script')
    write('source ${SUBMITTY_REPOSITORY}/.setup/INSTALL_SUBMITTY_HELPER.sh  "$@"')

os.chmod(CONFIGURATION_FILE, 700)

CONFIGURATION_JSON = os.path.join(SUBMITTY_INSTALL_DIR, '.setup', 'submitty_conf.json')
with open(CONFIGURATION_JSON, 'w') as json_file:
    obj = OrderedDict()
    obj['submitty_install_dir'] = SUBMITTY_INSTALL_DIR
    obj['submitty_repository'] = SUBMITTY_REPOSITORY
    obj['submitty_tutorial_dir'] = SUBMITTY_TUTORIAL_DIR
    obj['submitty_data_dir'] = SUBMITTY_DATA_DIR
    obj['hwphp_user'] = HWPHP_USER
    obj['hwcgi_user'] = HWCGI_USER
    obj['hwcron_user'] = HWCRON_USER
    obj['hwcronphp_group'] = HWCRONPHP_GROUP
    obj['course_builders_group'] = COURSE_BUILDERS_GROUP

    obj['num_untrusted'] = NUM_UNTRUSTED
    obj['first_untrusted_uid'] = FIRST_UNTRUSTED_UID
    obj['first_untrusted_gid'] = FIRST_UNTRUSTED_UID

    obj['hwcron_uid'] = HWCRON_UID
    obj['hwcron_gid'] = HWCRON_GID
    obj['hwphp_uid'] = HWPHP_UID
    obj['hwphp_gid'] = HWPHP_GID

    obj['database_host'] = DATABASE_HOST
    obj['database_user'] = DATABASE_USER
    obj['database_password'] = DATABASE_PASS

    obj['authentication_method'] = AUTHENTICATION_METHOD

    obj['submission_url'] = SUBMISSION_URL
    obj['tagrading_url'] = TAGRADING_URL
    obj['cgi_url'] = CGI_URL

    obj['autograding_log_path'] = AUTOGRADING_LOG_PATH
    obj['site_log_path'] = TAGRADING_LOG_PATH

    obj['max_instances_of_grade_students'] = MAX_INSTANCES_OF_GRADE_STUDENTS
    obj['grade_students_idle_seconds'] = GRADE_STUDENTS_IDLE_SECONDS
    obj['grade_students_idle_total_minutes'] = GRADE_STUDENTS_IDLE_TOTAL_MINUTES
    obj['grade_students_starts_per_hour'] = GRADE_STUDENTS_STARTS_PER_HOUR
    obj['debugging_enabled'] = DEBUGGING_ENABLED

    json.dump(obj, json_file, indent=2)
    json_file.write('\n')

os.chmod(CONFIGURATION_JSON, 700)

##############################################################################

print('Configuration completed. Now you may run the installation script')
print('    sudo ' + CONFIGURATION_FILE)
print('          or')
print('    sudo {} clean'.format(CONFIGURATION_FILE))
print("\n")
