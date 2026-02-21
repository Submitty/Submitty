"""Migration for a given Submitty course database."""
from pathlib import Path
import json

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
    sql = "ALTER TABLE queue_settings ADD COLUMN IF NOT EXISTS contact_information BOOLEAN NOT NULL DEFAULT TRUE;"
    database.execute(sql)
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
        # add boolean to course config
    config_file = Path(course_dir, 'config', 'config.json')
    if config_file.is_file():
        with config_file.open() as in_file:
            j = json.load(in_file)
        if 'queue_contact_info' in j['course_details']:
            contact_information_enabled = j['course_details']['queue_contact_info']
            query = """
                UPDATE queue_settings
                SET contact_information = :contact_information_enabled;
            """
            params = {'contact_information_enabled' : contact_information_enabled}
            database.session.execute(query, params)


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
