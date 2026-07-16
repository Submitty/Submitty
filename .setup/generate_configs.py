import argparse
from collections import OrderedDict
import json
import os
import shutil

parser = argparse.ArgumentParser(description='Submitty config generation script',
                                 formatter_class=argparse.ArgumentDefaultsHelpFormatter)
parser.add_argument('--worker', action='store_true', default=False,
                    help='Generate configs for only autograding')
parser.add_argument('--install-dir', default='/usr/local/submitty',
                    help='Set the install directory for Submitty')

args = parser.parse_args()

SUBMITTY_DATA_DIR = '/var/local/submitty'

CONFIG_INSTALL_DIR = os.path.join(args.install_dir, 'config')

CONFIG_DATA_DIR = os.path.join(SUBMITTY_DATA_DIR, 'config')

SETUP_REPOSITORY_DIR = os.path.join(args.install_dir, 'GIT_CHECKOUT/Submitty/.setup')

CONFIG_REPOSITORY = os.path.join(SETUP_REPOSITORY_DIR, 'data/configs')
if args.worker:
    CONFIG_REPOSITORY = os.path.join(SETUP_REPOSITORY_DIR, 'data/configs/worker')

SETUP_INSTALL_DIR = os.path.join(args.install_dir, '.setup')

os.makedirs(SETUP_INSTALL_DIR, exist_ok=True)
os.makedirs(SETUP_REPOSITORY_DIR, exist_ok=True)
os.makedirs(CONFIG_INSTALL_DIR, exist_ok=True)
os.makedirs(CONFIG_DATA_DIR, exist_ok=True)

PRESERVE_LIST_JSON = os.path.join(CONFIG_INSTALL_DIR, 'preserve_file_list.json')
# Rescue preserve list
preserve_list = OrderedDict()
try:
    with open(PRESERVE_LIST_JSON, 'r') as json_file:
        preserve_list = json.load(json_file, object_pairs_hook=OrderedDict)
except FileNotFoundError:
    pass
print("preserve list", preserve_list)

# preserve_list users json

with open(PRESERVE_LIST_JSON, 'w') as json_file:
    json.dump(preserve_list, json_file, indent=2)

# Copy all files from .setup/data/configs to the install config directory
for item in os.listdir(CONFIG_REPOSITORY):
    source_path = os.path.join(CONFIG_REPOSITORY, item)
    destination_path = os.path.join(CONFIG_INSTALL_DIR, item)
    # Only copy files, if the file does not exist at the destination
    if os.path.isfile(source_path) and not os.path.exists(destination_path):
        try:
            shutil.copy(source_path, destination_path)
        except PermissionError:
            print(f"Permission denied for '{item}'")
        except (shutil.Error, OSError) as e:
            print(f"An error occurred while copying '{item}': {e}")

# Copy items that need to be in /var/local instead of /usr/local
try:
    shutil.copy(os.path.join(CONFIG_REPOSITORY, 'var_configs/autograding_containers.json'), CONFIG_DATA_DIR)
except PermissionError:
    print(f"Permission denied for autograding_containers.json")
except (shutil.Error, OSError) as e:
    print(f"An error occurred while copying 'autograding_containers.json': {e}")

