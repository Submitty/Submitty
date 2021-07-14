"""Migration for the Submitty master database."""
from pathlib import Path
from sqlalchemy import text
import os

def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    database.execute(
    """
        CREATE TABLE IF NOT EXISTS courses_groups (
            semester varchar(255) NOT NULL,
            course varchar(255) NOT NULL,
            group_name varchar(255) NOT NULL,
            PRIMARY KEY (semester, course, group_name)
        )
    """
    )
    semester_dir = Path(config.submitty['submitty_data_dir'], 'courses')
    for semester in semester_dir.iterdir():
        for course in semester.iterdir():
            query = "INSERT INTO courses_groups (semester, course, group_name) VALUES (:s, :c, :g);"
            params = {
                's': semester.name,
                'c': course.name,
                'g': course.group()
            }
            database.session.execute(text(query), params)


def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    pass
