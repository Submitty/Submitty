import os
import grp
from pathlib import Path

def up(config, conn, semester, course):
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    reports_seating_dir = Path(course_dir, 'reports', 'seating')
    uploads_seating_dir = Path(course_dir, 'uploads', 'seating')

    # create the directories
    os.makedirs(str(reports_seating_dir), exist_ok=True)
    os.makedirs(str(uploads_seating_dir), exist_ok=True)

    php_user = config.submitty_users['php_user']

    # get course group
    stat_info = os.stat(str(course_dir))
    course_group_id = stat_info.st_gid
    course_group = grp.getgrgid(course_group_id)[0]

    # set the owner/permissions
    os.system("chown -R " + php_user + ":" + course_group + " " + str(reports_seating_dir))
    os.system("chmod -R u+rwx  " + str(reports_seating_dir))
    os.system("chmod -R g+rwxs " + str(reports_seating_dir))
    os.system("chmod -R o-rwx  " + str(reports_seating_dir))

    os.system("chown -R " + php_user + ":" + course_group + " " + str(uploads_seating_dir))
    os.system("chmod -R u+rwx  " + str(uploads_seating_dir))
    os.system("chmod -R g+rwxs " + str(uploads_seating_dir))
    os.system("chmod -R o-rwx  " + str(uploads_seating_dir))


def down(config, conn, semester, course):
    pass
