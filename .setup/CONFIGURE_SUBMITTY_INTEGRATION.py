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

SUBMIT
