import os
import grp
import json
from pathlib import Path


def up(config, conn, semester, course):
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)


    # add boolean to course config
    config_file = Path(course_dir, 'config', 'config.json')
    if config_file.is_file():

        with open(config_file, 'r') as in_file:

            j = json.load(in_file)

        if 'auto_rainbow_grades' not in j['course_details']:
            j['course_details']['auto_rainbow_grades'] = False

        with open(config_file, 'w') as out_file:
            json.dump(j, out_file, indent=4)

    
    # create the directories
    course_rainbow_grades_dir = Path(course_dir, 'rainbow_grades')
    os.makedirs(str(course_rainbow_grades_dir), exist_ok=True)

    php_user = config.submitty_users['php_user']

    # get course group
    stat_info = os.stat(str(course_dir))
    course_group_id = stat_info.st_gid
    course_group = grp.getgrgid(course_group_id)[0]

    # set the owner/group/permissions
    os.system("chown -R "+php_user+":"+course_group+" "+str(course_rainbow_grades_dir))
    os.system("chmod -R u+rwx  "+str(course_rainbow_grades_dir))
    os.system("chmod -R g+rwxs "+str(course_rainbow_grades_dir))
    os.system("chmod -R o-rwx  "+str(course_rainbow_grades_dir))


def down(config, conn, semester, course):
    pass
