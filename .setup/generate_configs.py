import argparse
import os
import shutil
import json

parser = argparse.ArgumentParser(description='Submitty config validation script',
                                 formatter_class=argparse.ArgumentDefaultsHelpFormatter)
parser.add_argument('--debug', action='store_true', default=False, help='Configure Submitty to be in debug mode. '
                                                                        'This should not be used in production!')
parser.add_argument('--worker', action='store_true', default=False, help='Configure Submitty with autograding only')
parser.add_argument('--install-dir', default='/usr/local/submitty', help='Set the install directory for Submitty')
parser.add_argument('--data-dir', default='/var/local/submitty', help='Set the data directory for Submitty')
parser.add_argument('--ci', action='store_true', default=False, help='Flag for running Submitty in CI, since it uses different parameters')

args = parser.parse_args()

CONFIG_INSTALL_DIR = os.path.join(args.install_dir, 'config')
os.makedirs(CONFIG_INSTALL_DIR, exist_ok=True)
CONFIG_REPOSITORY = os.path.join(args.install_dir, 'GIT_CHECKOUT/Submitty/.setup/data/configs')

SETUP_INSTALL_DIR = os.path.join(args.install_dir, '.setup')
os.makedirs(SETUP_INSTALL_DIR, exist_ok=True)

SETUP_REPOSITORY_DIR = os.path.join(args.install_dir, 'GIT_CHECKOUT/Submitty/.setup')
os.makedirs(SETUP_REPOSITORY_DIR, exist_ok=True)

INSTALL_FILE = os.path.join(SETUP_INSTALL_DIR, 'INSTALL_SUBMITTY.sh')

if args.ci is True:
    database_config = os.path.join(CONFIG_REPOSITORY, 'database.json')
    authentication_config = os.path.join(CONFIG_REPOSITORY, 'authentication.json')
    with open(database_config, 'r', encoding='utf-8') as f:
        database_json = json.load(f)

    database_json['authentication_method'] = 'DatabaseAuthentication'
    database_json['database_host'] = 'localhost'

    with open(database_config, 'w', encoding='utf-8') as f:
        json.dump(database_json, f, indent=2)

    with open(authentication_config, 'r', encoding='utf-8') as f:
        authentication_json = json.load(f)

    authentication_json['authentication_method'] = 'DatabaseAuthentication'

    with open(authentication_config, 'w', encoding='utf-8') as f:
        json.dump(authentication_json, f, indent=2)


if not args.worker:
    for item in os.listdir(CONFIG_REPOSITORY):
        source_path = os.path.join(CONFIG_REPOSITORY, item)
        destination_path = os.path.join(CONFIG_INSTALL_DIR, item)
        # Check if the item is a file before copying
        if os.path.isfile(source_path) and not os.path.exists(destination_path):
            try:
                shutil.copy(source_path, destination_path)
                print(f"Copied '{item}'")
            except PermissionError:
                print(f"Permission denied for '{item}'")
            except Exception as e:
                print(f"An error occurred while copying '{item}': {e}")
