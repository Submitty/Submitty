# TODO: Modify this script to include main() and test

import os
import shutil
import datetime from datatime

# Install all sample user images to the user_data directory
images_dir = os.path.join(SUBMITTY_DATA_DIR, 'user_data')
sample_images_dir = os.path.join(SUBMITTY_REPOSITORY, 'sample_files', 'user_photos')

# Clean up any old installations
shutil.rmtree(images_dir)

# Unzip each images zip inside temp
for zip_file in os.listdir(sample_images_dir):

    zip_path = os.path.join(sample_images_dir, zip_file)

    with ZipFile(zip_path, 'r') as zipObj:
        zipObj.extractall('temp')

time_stamp = datetime.now()

# Traverse subdirectories for images
for sub_dir in os.listdir('temp'):
    for file in os.listdir(os.path.join('temp', sub_dir)):

        # If file is an image, create a folder for them in the image_uploads dir
        # Only need one image per user, so ignore duplicates
        extension = file[-3:]
        user_name = file[:-4]

        if extension == 'png':
            user_images_path = os.path.join(images_dir, user_name, 'system_images')
            new_file_name = time_stamp.strftime("%Y%m%d") + '.png'

            if not os.path.isdir(user_images_path):
                src = os.path.join('.', 'temp', sub_dir, file)
                dest = os.path.join(user_images_path, new_file_name)

                os.makedirs(user_images_path)
                shutil.copy(src, dest)

# Clean up temp
shutil.rmtree('temp')

exit()
