import os
import grp
from pathlib import Path


def up(config, conn, semester, course):
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    images_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course, 'uploads', 'student_images')

    # create the directories
    os.makedirs(str(images_dir), exist_ok=True)

    # get course group
    stat_info = os.stat(str(course_dir))
    course_group_id = stat_info.st_gid
    course_group = grp.getgrgid(course_group_id)[0]

    # set the owner/group/permissions
    os.system("chown -R hwphp:"+course_group+" "+str(images_dir))
    os.system("chmod -R u+rwx  "+str(images_dir))
    os.system("chmod -R g+rwxs "+str(images_dir))
    os.system("chmod -R o-rwx  "+str(images_dir))


def down(config, conn, semester, course):
    pass
