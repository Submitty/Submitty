"""Migration for a given Submitty course database."""

import os
import grp
import pwd
from pathlib import Path
import shutil

def up(config, database, semester, course):

    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    solution_dir = Path(course_dir, 'instructor_solution')

    # get course group
    stat_info = os.stat(str(course_dir))
    course_group_id = stat_info.st_gid
    uid = stat_info.st_uid
    course_group = grp.getgrgid(course_group_id)[0]
    user = pwd.getpwuid(uid)[0]

    if not os.path.exists(solution_dir):
        os.mkdir(solution_dir)
        shutil.chown(solution_dir, user=user, group=course_group)
        os.system("chmod u+rwx {0}".format(str(solution_dir)))
        os.system("chmod g+rwx {0}".format(str(solution_dir)))
        os.system("chmod g+s {0}".format(str(solution_dir)))
        os.system("chmod o-rwx {0}".format(str(solution_dir)))


def down(config, database, semester, course):
    #The existence of an old solution directory isn't harmful.
    pass
