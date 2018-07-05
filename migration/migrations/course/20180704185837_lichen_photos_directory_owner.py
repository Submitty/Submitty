import os
import grp
from pathlib import Path


def up(config, conn, semester, course):

    # Redo permissions from previous migrations (in case of error
    # related to system user name changes)
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    images_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course, 'uploads', 'student_images')
    lichen_dir = Path(course_dir, 'lichen')
    
    # get course group
    stat_info = os.stat(str(course_dir))
    course_group_id = stat_info.st_gid
    course_group = grp.getgrgid(course_group_id)[0]

    # set the owner/group/permissions
    os.system("chown -R submitty_php:"+course_group+" "+str(images_dir))
    os.system("chown -R submitty_php:"+course_group+" "+str(lichen_dir))

    pass


def down(config, conn, semester, course):
    pass
