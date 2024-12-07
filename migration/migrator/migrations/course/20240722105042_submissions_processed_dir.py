"""Migration for a given Submitty course database."""

import os
import pwd 
import stat
from pathlib import Path


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
    submissions_processed_dir = Path(course_dir, 'submissions_processed')

    os.makedirs(str(submissions_processed_dir), exist_ok=True)

    daemon_user = config.submitty_users['daemon_user']
    daemon_user_id = pwd.getpwnam(daemon_user).pw_uid

    stat_info = os.stat(str(course_dir))
    course_group_id = stat_info.st_gid

    os.chown(submissions_processed_dir, daemon_user_id, course_group_id)
    os.chmod(submissions_processed_dir, stat.S_IRWXU | stat.S_IRWXG | stat.S_ISGID)


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
