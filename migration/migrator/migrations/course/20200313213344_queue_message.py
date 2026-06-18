"""Migration for a given Submitty course database."""
import json
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
    # add boolean to course config
    config_file = Path(course_dir, 'config', 'config.json')
    if config_file.is_file():
        with open(config_file, 'r') as in_file:
            j = json.load(in_file)

        if 'queue_message' not in j['course_details']:
            j['course_details']['queue_message'] = ''

        with open(config_file, 'w') as out_file:
            json.dump(j, out_file, indent=4)


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

    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    # add boolean to course config
    config_file = Path(course_dir, 'config', 'config.json')
    if config_file.is_file():
        with open(config_file, 'r') as in_file:
            j = json.load(in_file)

        if 'queue_message' in j['course_details']:
            del j['course_details']['queue_message']

        with open(config_file, 'w') as out_file:
            json.dump(j, out_file, indent=4)
