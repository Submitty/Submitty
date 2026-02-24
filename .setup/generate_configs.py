import argparse
import os
import shutil
import json

parser = argparse.ArgumentParser(description='Submitty config validation script',
                                 formatter_class=argparse.ArgumentDefaultsHelpFormatter)
parser.add_argument('--worker', action='store_true', default=False,
                    help='Configure Submitty with autograding only')
parser.add_argument('--install-dir', default='/usr/local/submitty',
                    help='Set the install directory for Submitty')

args = parser.parse_args()

CONFIG_INSTALL_DIR = os.path.join(args.install_dir, 'config')

SETUP_REPOSITORY_DIR = os.path.join(args.install_dir, 'GIT_CHECKOUT/Submitty/.setup')

CONFIG_REPOSITORY = os.path.join(SETUP_REPOSITORY_DIR, 'data/configs')

SETUP_INSTALL_DIR = os.path.join(args.install_dir, '.setup')

os.makedirs(SETUP_INSTALL_DIR, exist_ok=True)
os.makedirs(SETUP_REPOSITORY_DIR, exist_ok=True)
os.makedirs(CONFIG_INSTALL_DIR, exist_ok=True)
if not args.worker:
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
