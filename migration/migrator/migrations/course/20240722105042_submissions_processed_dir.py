"""Migration for a given Submitty course database."""

import os
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

    stat_info = os.stat(str(course_dir))
    course_group_id = stat_info.st_gid

    os.system(f"chown {daemon_user}:{course_group_id} {submissions_processed_dir}")
    os.system(f"chmod u+rwx {submissions_processed_dir}")
    os.system(f"chmod g+rwxs {submissions_processed_dir}")
    os.system(f"chmod o-rwx {submissions_processed_dir}")


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
