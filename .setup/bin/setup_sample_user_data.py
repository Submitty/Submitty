#!/usr/bin/env python3

import os
import shutil
from datetime import datetime
from zipfile import ZipFile

PHP_USER = 'submitty_php'
SUBMITTY_INSTALL_DIR = "/usr/local/submitty"
SUBMITTY_DATA_DIR = "/var/local/submitty"
SUBMITTY_REPOSITORY = os.path.join(SUBMITTY_INSTALL_DIR, "GIT_CHECKOUT/Submitty")


def main():

    user_data_dir = os.path.join(SUBMITTY_DATA_DIR, 'user_data')
    sample_images_dir = os.path.join(SUBMITTY_REPOSITORY, 'sample_files', 'user_photos')

    # Clean up any old installations
    for item in os.listdir(user_data_dir):
        shutil.rmtree(os.path.join(user_data_dir, item))

    # Unzip the sample_files image archives
    for zip_file in os.listdir(sample_images_dir):

        zip_path = os.path.join(sample_images_dir, zip_file)

        with ZipFile(zip_path, 'r') as zipObj:
            zipObj.extractall('temp')

    time_stamp = datetime.now()

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
                new_file_name = time_stamp.strftime("%Y%m%d") + '.png'
                access = 0o770

                if not os.path.isdir(user_folder_path):
                    os.makedirs(user_folder_path)
                    os.chmod(user_folder_path, access)
                    shutil.chown(user_folder_path, PHP_USER, PHP_USER)

                if not os.path.isdir(user_images_path):
                    os.makedirs(user_images_path)
                    os.chmod(user_images_path, access)
                    shutil.chown(user_images_path, PHP_USER, PHP_USER)

                    src = os.path.join('.', 'temp', sub_dir, file)
                    dest = os.path.join(user_images_path, new_file_name)

                    shutil.copy(src, dest)
                    shutil.chown(dest, PHP_USER, PHP_USER)

    # Clean up temp
    shutil.rmtree('temp')


if __name__ == "__main__":
    main()
