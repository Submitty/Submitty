import os
import grp
from pathlib import Path


# the submitty_php user also needs write access to the uploads
# directory, so it can write the course_materials_file_data.json file
# (we forgot this in the previous course_materials_directory
# migration)


def up(config, database, semester, course):
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    uploads_dir = Path(course_dir, 'uploads')

    php_user = config.submitty_users['php_user']

    # get course group
    stat_info = os.stat(str(course_dir))
    course_group_id = stat_info.st_gid
    course_group = grp.getgrgid(course_group_id)[0]

    # set the owner/group/permissions
    os.system("chown "+php_user+":"+course_group+" "+str(uploads_dir))
    os.system("chmod u+rwx  "+str(uploads_dir))
    os.system("chmod g+rxs  "+str(uploads_dir))
    os.system("chmod o-rwx  "+str(uploads_dir))
