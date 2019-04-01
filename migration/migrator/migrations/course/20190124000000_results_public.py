import os
import json
import shutil
import grp
from pathlib import Path

def up(config, conn, semester, course):
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    results_public_dir = Path(course_dir, 'results_public')
    
    # mkdir
    os.makedirs(str(results_public_dir), exist_ok=True)

    # get course group
    stat_info = os.stat(str(course_dir))
    course_group_id = stat_info.st_gid
    course_group = grp.getgrgid(course_group_id)[0]

    daemon_user = config.submitty_users['daemon_user']

    # set owner & permissions of this directory
    os.system("chown -R " + daemon_user + ":" + course_group + " " + str(results_public_dir))
    os.system("chmod -R u+rwx "+str(results_public_dir))
    os.system("chmod -R g+rwx "+str(results_public_dir))
    os.system("chmod -R o-rwx "+str(results_public_dir))

    pass


def down(config, conn, semester, course):
    pass
