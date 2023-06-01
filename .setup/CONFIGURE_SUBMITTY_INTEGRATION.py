#!/usr/bin/env python3

import argparse
from collections import OrderedDict
import grp
import json
import os
import pwd
import secrets
import shutil
import string
import tzlocal
import tempfile


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


class StrToBoolAction(argparse.Action):
    """
    Custom action that parses strings to boolean values. All values that come
    from bash are strings, and so need to parse that into the appropriate
    bool value.
    """
    def __init__(self, option_strings, dest, nargs=None, **kwargs):
        if nargs is not None:
            raise ValueError("nargs not allowed")
        super().__init__(option_strings, dest, **kwargs)

    def __call__(self, parser, namespace, values, option_string=None):
        setattr(namespace, self.dest, values != '0' and values.lower() != 'false')


##############################################################################
# this script must be run by root or sudo
if os.getuid() != 0:
    raise SystemExit('ERROR: This script must be run by root or sudo')


parser = argparse.ArgumentParser(description='Submitty configuration script',
                                 formatter_class=argparse.ArgumentDefaultsHelpFormatter)
parser.add_argument('--debug', action='store_true', default=False, help='Configure Submitty to be in debug mode. '
                                                                        'This should not be used in production!')
parser.add_argument('--setup-for-sample-courses', action='store_true', default=False,
                    help="Sets up Submitty for use with the sample courses. This is a Vagrant convenience "
                         "flag and should not be used in production!")
parser.add_argument('--worker', action='store_true', default=False, help='Configure Submitty with autograding only')
parser.add_argument(
    '--worker-pair',
    default=False,
    action=StrToBoolAction,
    help='Configure Submitty alongside a worker VM. This should only be used during development using Vagrant.'
)
parser.add_argument('--install-dir', default='/usr/local/submitty', help='Set the install directory for Submitty')
parser.add_argument('--data-dir', default='/var/local/submitty', help='Set the data directory for Submitty')
parser.add_argument('--websocket-port', default=8443, type=int, help='Port to use for websocket')

args = parser.parse_args()

# determine location of SUBMITTY GIT repository
# this script (CONFIGURES_SUBMITTY.py) is in the top level directory of the repository
# (this command works even if we run configure from a different directory)
SETUP_SCRIPT_DIRECTORY = os.path.dirname(os.path.realpath(__file__))
SUBMITTY_REPOSITORY = os.path.dirname(SETUP_SCRIPT_DIRECTORY)

# recommended (default) directory locations
# FIXME: Check that directories exist and are readable/writeable?
SUBMITTY_INSTALL_DIR = args.install_dir
if not os.path.isdir(SUBMITTY_INSTALL_DIR) or not os.access(SUBMITTY_INSTALL_DIR, os.R_OK | os.W_OK):
    raise SystemExit('Install directory {} does not exist or is not accessible'.format(SUBMITTY_INSTALL_DIR))

SUBMITTY_DATA_DIR = args.data_dir
if not os.path.isdir(SUBMITTY_DATA_DIR) or not os.access(SUBMITTY_DATA_DIR, os.R_OK | os.W_OK):
    raise SystemExit('Data directory {} does not exist or is not accessible'.format(SUBMITTY_DATA_DIR))

TAGRADING_LOG_PATH = os.path.join(SUBMITTY_DATA_DIR, 'logs')
AUTOGRADING_LOG_PATH = os.path.join(SUBMITTY_DATA_DIR, 'logs', 'autograding')

WEBSOCKET_PORT = args.websocket_port

##############################################################################

# recommended names for special users & groups related to the SUBMITTY system
PHP_USER = 'submitty_php'
PHP_GROUP = 'submitty_php'
CGI_USER = 'submitty_cgi'
DAEMON_USER = 'submitty_daemon'
DAEMON_GROUP = 'submitty_daemon'

if not args.worker:
    PHP_UID, PHP_GID = get_ids(PHP_USER)
    CGI_UID, CGI_GID = get_ids(CGI_USER)
    # System Groups
    DAEMONPHP_GROUP = 'submitty_daemonphp'
    try:
        grp.getgrnam(DAEMONPHP_GROUP)
    except KeyError:
        raise SystemExit("ERROR: Could not find group: " + DAEMONPHP_GROUP)
    DAEMONCGI_GROUP = 'submitty_daemoncgi'
    try:
        grp.getgrnam(DAEMONCGI_GROUP)
    except KeyError:
        raise SystemExit("ERROR: Could not find group: " + DAEMONCGI_GROUP)

DAEMON_UID, DAEMON_GID = get_ids(DAEMON_USER)

COURSE_BUILDERS_GROUP = 'submitty_course_builders'
try:
    grp.getgrnam(COURSE_BUILDERS_GROUP)
except KeyError:
    raise SystemExit("ERROR: Could not find group: " + COURSE_BUILDERS_GROUP)

##############################################################################

# This is the upper limit of the number of parallel grading threads on
# this machine
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
NUM_GRADING_SCHEDULER_WORKERS = 5

##############################################################################

SETUP_INSTALL_DIR = os.path.join(SUBMITTY_INSTALL_DIR, '.setup')
SETUP_REPOSITORY_DIR = os.path.join(SUBMITTY_REPOSITORY, '.setup')

INSTALL_FILE = os.path.join(SETUP_INSTALL_DIR, 'INSTALL_SUBMITTY.sh')
CONFIGURATION_JSON = os.path.join(SETUP_INSTALL_DIR, 'submitty_conf.json')
SITE_CONFIG_DIR = os.path.join(SUBMITTY_INSTALL_DIR, "site", "config")
CONFIG_INSTALL_DIR = os.path.join(SUBMITTY_INSTALL_DIR, 'config')
SUBMITTY_ADMIN_JSON = os.path.join(CONFIG_INSTALL_DIR, 'submitty_admin.json')
EMAIL_JSON = os.path.join(CONFIG_INSTALL_DIR, 'email.json')
AUTHENTICATION_JSON = os.path.join(CONFIG_INSTALL_DIR, 'authentication.json')

##############################################################################
print('Configuration completed. Now you may run the installation script')
print(f'    sudo {INSTALL_FILE}')
print('          or')
print(f'    sudo {INSTALL_FILE} clean')
print("\n")
