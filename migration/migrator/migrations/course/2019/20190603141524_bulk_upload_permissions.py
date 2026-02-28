"""Migration for a given Submitty course database."""

import os
import grp
from pathlib import Path

def up(config, database, semester, course):

    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    uploads_dir = Path(course_dir, 'uploads')
    bulk_uploads_dir = Path(course_dir, 'uploads', 'bulk_pdf')
    split_uploads_dir = Path(course_dir, 'uploads', 'split_pdf')

    php_user = config.submitty_users['php_user']
    daemon_user = config.submitty_users['daemon_user']

    # get course group
    stat_info = os.stat(str(course_dir))
    course_group_id = stat_info.st_gid
    course_group = grp.getgrgid(course_group_id)[0]

    # desired permissions from create_course.sh
    #  drwxr-s---       $DAEMON_USER     ta_www_group    uploads/
    #  drwxr-s---       $PHP_USER        ta_www_group    uploads/bulk_pdf/
    #  drwxrws---       $DAEMON_USER     ta_www_group    uploads/split_pdf/

    # set the owner/group/permissions
    os.system("chown "+daemon_user+":"+course_group+" "+str(uploads_dir))
    os.system("chmod u+rwx  "+str(uploads_dir))
    os.system("chmod g+rxs  "+str(uploads_dir))
    os.system("chmod o-rwx  "+str(uploads_dir))

    os.system("chown "+php_user+":"+course_group+" "+str(bulk_uploads_dir))
    os.system("chmod -R u+rwx  "+str(bulk_uploads_dir))
    os.system("chmod -R g+rxs  "+str(bulk_uploads_dir))
    os.system("chmod -R o-rwx  "+str(bulk_uploads_dir))

    os.system("chown "+daemon_user+":"+course_group+" "+str(split_uploads_dir))
    os.system("chmod -R u+rwx  "+str(split_uploads_dir))
    os.system("chmod -R g+rwxs "+str(split_uploads_dir))
    os.system("chmod -R o-rwx  "+str(split_uploads_dir))

    pass


def down(config, database, semester, course):

    pass
