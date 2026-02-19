import os
import argparse
import pwd
import json

if os.getuid() != 0:
    raise SystemExit('ERROR: This script must be run by root or sudo')

parser = argparse.ArgumentParser(description='Submitty config validation script',
                                 formatter_class=argparse.ArgumentDefaultsHelpFormatter)
parser.add_argument('--debug', action='store_true', default=False, help='Configure Submitty to be in debug mode. '
                                                                        'This should not be used in production!')
parser.add_argument('--worker', action='store_true', default=False, help='Configure Submitty with autograding only')
parser.add_argument('--install-dir', default='/usr/local/submitty', help='Set the install directory for Submitty')
parser.add_argument('--data-dir', default='/var/local/submitty', help='Set the data directory for Submitty')

args = parser.parse_args()

SUBMITTY_INSTALL_DIR = args.install_dir
if not os.path.isdir(SUBMITTY_INSTALL_DIR) or not os.access(SUBMITTY_INSTALL_DIR, os.R_OK | os.W_OK):
    raise SystemExit('Install directory {} does not exist or is not accessible'.format(SUBMITTY_INSTALL_DIR))


SUBMITTY_DATA_DIR = args.data_dir 
os.makedirs(SUBMITTY_DATA_DIR, exist_ok=True)
INSTALL_FILE = os.path.join(SUBMITTY_INSTALL_DIR, '.setup/INSTALL_SUBMITTY.sh')

CONFIG_INSTALL_DIR = os.path.join(SUBMITTY_INSTALL_DIR, 'config')
os.makedirs(os.path.join(SUBMITTY_INSTALL_DIR, '.setup'), exist_ok=True)
CONFIG_REPOSITORY = os.path.join(args.install_dir, 'GIT_CHECKOUT/Submitty/.setup/data/configs')

if not args.worker:
    for item in os.listdir(CONFIG_REPOSITORY):
        source_path = os.path.join(CONFIG_REPOSITORY, item)
        destination_path = os.path.join(CONFIG_INSTALL_DIR, item)
        if os.path.isfile(source_path):
            with open(source_path, 'r') as f1, open(destination_path, 'r') as f2:
                try:
                    required = json.load(f1).keys()
                    existing = json.load(f2).keys()
                    difference = required - existing
                    if len(difference) > 0:
                        raise KeyError("Required key(s) {} not present in {}".format(difference, item))
                except FileNotFoundError:
                    raise FileNotFoundError("Required file {} not found".format(destination_path))


SETUP_REPOSITORY_DIR = os.path.join(args.install_dir, 'GIT_CHECKOUT/Submitty/.setup')

with open(INSTALL_FILE, 'w') as open_file:
    def write(x=''):
        print(x, file=open_file)
    write('#!/bin/bash')
    write()
    write(f'bash {SETUP_REPOSITORY_DIR}/INSTALL_SUBMITTY_HELPER.sh  "$@"')

os.chmod(INSTALL_FILE, 0o700)