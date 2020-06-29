#!/usr/bin/env python3
"""
Setup script that scans over the user_photo zips inside the submitty repo sample files and then generates sample data
in the user_data directory.  We are only grabbing one image from each zip.
"""

import os
import shutil
import json
from zipfile import ZipFile
from datetime import datetime

def main():

    # Get path to current file directory
    current_dir = os.path.dirname(os.path.abspath(__file__))

    # Collect other path information from configuration file
    config_file = os.path.join(current_dir, '..', '..', '..', '..', 'config', 'submitty.json')
    submitty_users_file = os.path.join(current_dir, '..', '..', '..', '..', 'config',
                                       'submitty_users.json')

    with open(config_file) as f:
        data = json.load(f)
        submitty_data_dir = data['submitty_data_dir']
        submitty_repository = data['submitty_repository']

    with open(submitty_users_file) as f:
        data = json.load(f)
        php_user = data['php_user']

    user_data_dir = os.path.join(submitty_data_dir, 'user_data')
    sample_images_dir = os.path.join(submitty_repository, 'sample_files', 'user_photos')

    # Clean up any old installations
    for item in os.listdir(user_data_dir):
        shutil.rmtree(os.path.join(user_data_dir, item))

    # Unzip the sample_files image archives
    for zip_file in os.listdir(sample_images_dir):

        zip_path = os.path.join(sample_images_dir, zip_file)

        with ZipFile(zip_path, 'r') as zipObj:
            zipObj.extractall('temp')

    # Traverse subdirectories for images
    for sub_dir in os.listdir('temp'):
        for file in os.listdir(os.path.join('temp', sub_dir)):

            # If file is an image, create a folder for them in the user_data dir
            # Only need one image per user, so ignore duplicates
            extension = file[-3:]
            user_name = file[:-4]

            if extension == 'png':
                user_folder_path = os.path.join(user_data_dir, user_name)
                user_images_path = os.path.join(user_data_dir, user_name, 'system_images')
                access = 0o770

                time = datetime.now()
                new_file_name = time.strftime('%Y%m%d%H%M%S') + '.png'

                if not os.path.isdir(user_folder_path):
                    os.makedirs(user_folder_path)
                    os.chmod(user_folder_path, access)
                    shutil.chown(user_folder_path, php_user, php_user)

                if not os.path.isdir(user_images_path):
                    os.makedirs(user_images_path)
                    os.chmod(user_images_path, access)
                    shutil.chown(user_images_path, php_user, php_user)

                    src = os.path.join('.', 'temp', sub_dir, file)
                    dest = os.path.join(user_images_path, new_file_name)

                    shutil.copy(src, dest)
                    shutil.chown(dest, php_user, php_user)

            # Only do one image from each zip
            break

    # Clean up temp
    shutil.rmtree('temp')


if __name__ == "__main__":
    main()
