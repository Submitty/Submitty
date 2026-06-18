import os
import grp
from pathlib import Path

def up(config, database, semester, course):
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    site_wrapper_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course, 'site')

    # create the directories
    os.makedirs(str(site_wrapper_dir), exist_ok=True)

    # get course group
    stat_info = os.stat(str(course_dir))
    course_group_id = stat_info.st_gid
    course_group = grp.getgrgid(course_group_id)[0]

    # set the owner/group/permissions
    os.system("chown -R submitty_php:"+course_group+" "+str(site_wrapper_dir))
    os.system("chmod -R 770 "+str(site_wrapper_dir))
