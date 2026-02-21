import os
import grp
from pathlib import Path


def up(config, conn, semester, course):
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    uploads_dir = Path(course_dir, 'uploads')

    php_user = config.submitty_users['php_user']

    # set the owner
    os.system("chown "+php_user+" "+str(uploads_dir))

    pass


def down(config, conn, semester, course):
    pass
