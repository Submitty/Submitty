import os
import grp
from pathlib import Path

def up(config, database, semester, course):
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course, 'reports')
    polls_dir = Path(course_dir, 'polls')

    #create directory
    os.mkdir(polls_dir)

    php_user = config.submitty_users['php_user']

    # get course group
    stat_info = os.stat(str(course_dir))
    course_group_id = stat_info.st_gid
    course_group = grp.getgrgid(course_group_id)[0]

    # set the owner/group/permissions
    os.system("chown -R "+php_user+":"+course_group+" "+str(polls_dir))
    os.system("chmod -R u+rwx "+str(polls_dir))
    os.system("chmod -R g+rxs "+str(polls_dir))
    os.system("chmod -R o-rwx "+str(polls_dir))


def down(config, database, semester, course):
    pass
