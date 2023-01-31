import shutil
from pathlib import Path

def up(config, database, term, course):
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', term, course, 'reports')
    polls_dir = Path(course_dir, 'polls')

    #create directory
    polls_dir.mkdir(mode=0o750, exist_ok=True)

    php_user = config.submitty_users['php_user']

    # get course group
    course_group_id = course_dir.stat().st_gid

    # set the owner/group/permissions
    shutil.chown(polls_dir, php_user, course_group_id)


def down(config, database, term, course):
    pass
