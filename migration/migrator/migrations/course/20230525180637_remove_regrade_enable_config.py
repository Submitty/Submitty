"""Migration for a given Submitty course database."""
import os
import json

def course_config_location(semester, course):
    """
    Returns path to course config file for a given semester and course.
    
    :param semester: Semester of the course being migrated
    :type semester: str
    :param course: Code of course being migrated
    :type course: str
    """
    return f"/var/local/submitty/courses/{semester}/{course}/config/config.json"


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

    path = course_config_location(semester, course)
    if not os.path.isfile(path) or os.path.getsize(path) == 0: # Empty file
        return

    with open(path, "r") as config_file:
        # Get regrade_enabled setting
        course_config = json.load(config_file)
        was_regrade_enabled = course_config["course_details"]["regrade_enabled"]

    # If course did not have inquiries, then the gradeables each don't want them
    if was_regrade_enabled == False:
        database.execute("""
            UPDATE electronic_gradeable
            SET eg_regrade_allowed = 'false'
        """)


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

    path = course_config_location(semester, course)
    if not os.path.isfile(path) or os.path.getsize(path) == 0:
        return


    with open(path, "r") as config_file:
        # Set previously-used regrade_enabled setting, but as true as info was lost
        course_config = json.load(config_file)
    
    course_config["course_details"]["regrade_enabled"] = True

    with open(path, "w") as config_file: # Separate read and write to avoid appending at end of file
        json.dump(course_config, config_file, indent=4)