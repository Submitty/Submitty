import os
import grp
from pathlib import Path
import shutil


def up(config, database, semester, course):
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    course_materials_dir = Path(course_dir, 'uploads', 'course_materials')
    banner_images_dir = Path(course_materials_dir, 'banner_images')

    # create the directories
    os.makedirs(str(banner_images_dir), exist_ok=True)


    php_user = config.submitty_users['php_user']

    # get course group
    stat_info = os.stat(str(course_dir))
    course_group_id = stat_info.st_gid
    course_group = grp.getgrgid(course_group_id)[0]

    # set the owner/group/permissions
    os.system("chown -R "+php_user+":"+course_group+" "+str(banner_images_dir))
    os.system("chmod -R u+rwx  "+str(banner_images_dir))
    os.system("chmod -R g+rwxs "+str(banner_images_dir))
    os.system("chmod -R o-rwx  "+str(banner_images_dir))


    query = "ALTER TABLE course_materials ADD COLUMN IF NOT EXISTS banner_closed BOoLEAN NOT NULL DEFAULT FALSE;";
    database.execute(query);




def down(config, database, semester, course):
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    course_materials_dir = Path(course_dir, 'uploads', 'course_materials')
    banner_images_dir = Path(course_materials_dir, 'banner_images')

    if os.path.exists(str(banner_images_dir)):
        # Delete all contents within banner_images directory
        shutil.rmtree(str(banner_images_dir))

    query = "ALTER TABLE course_materials DROP COLUMN IF EXISTS banner_closed;"
    database.execute(query);
