import os
import grp
from pathlib import Path
"""Migration for a given Submitty course database."""


def up(config, database, semester, course):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    :param semester: Semester of the course being migrated
    :type semester: str
    :param course: Code of course being migrated
    :type course: str
    """
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    attachments_dir = Path(course_dir, 'attachments')
    # create the directories
    os.makedirs(str(attachments_dir), exist_ok=True)

    php_user = config.submitty_users['php_user']

    # get course group
    stat_info = os.stat(str(course_dir))
    course_group_id = stat_info.st_gid
    course_group = grp.getgrgid(course_group_id)[0]

    # set the owner/group/permissions
    os.system("chown -R "+php_user+":"+course_group+" "+str(attachments_dir))
    os.system("chmod -R u+rwx "+str(attachments_dir))
    os.system("chmod -R g+rxs "+str(attachments_dir))
    os.system("chmod -R o-rwx "+str(attachments_dir))


def down(config, database, semester, course):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    :param semester: Semester of the course being migrated
    :type semester: str
    :param course: Code of course being migrated
    :type course: str
    """
    pass
