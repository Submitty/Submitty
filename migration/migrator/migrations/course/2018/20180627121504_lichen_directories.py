import os
import grp
from pathlib import Path


def up(config, database, semester, course):
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    lichen_dir = Path(course_dir, 'lichen')
    lichen_config_dir = Path(lichen_dir, 'config')
    lichen_provided_dir = Path(lichen_dir, 'provided_code')

    # create the directories
    os.makedirs(str(lichen_dir), exist_ok=True)
    os.makedirs(str(lichen_config_dir), exist_ok=True)
    os.makedirs(str(lichen_provided_dir), exist_ok=True)

    # get course group
    stat_info = os.stat(str(course_dir))
    course_group_id = stat_info.st_gid
    course_group = grp.getgrgid(course_group_id)[0]

    # set the owner/group/permissions
    os.system("chown -R hwphp:"+course_group+" "+str(lichen_dir))
    os.system("chmod -R u+rwx  "+str(lichen_dir))
    os.system("chmod -R g+rwxs "+str(lichen_dir))
    os.system("chmod -R o-rwx  "+str(lichen_dir))
