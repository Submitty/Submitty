"""Migration for a given Submitty course database."""
import os
from pathlib import Path


def up(config, database, semester, course):

    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)

    course_rainbow_grades_dir = Path(course_dir, 'rainbow_grades')

    # add group write permission, missing from new courses
    os.system("chmod -R g+rwxs "+str(course_rainbow_grades_dir))


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
