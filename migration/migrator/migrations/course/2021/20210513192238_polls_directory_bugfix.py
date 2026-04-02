import shutil
from pathlib import Path


def up(config, database, semester, course):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course, 'uploads')
    polls_dir = Path(course_dir, 'polls')
    polls_dir.mkdir(mode=0o750, exist_ok=True)
    php_user = config.submitty_users['php_user']
    # get course group
    course_group_id = course_dir.stat().st_gid
    # set the owner/group/permissions
    shutil.chown(polls_dir, php_user, course_group_id)



def down(config, database, semester, course):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
