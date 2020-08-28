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
    database.execute("CREATE TABLE IF NOT EXISTS polls(poll_id SERIAL PRIMARY KEY, name TEXT NOT NULL, question TEXT NOT NULL, status TEXT NOT NULL, release_date DATE NOT NULL)")
    database.execute("CREATE TABLE IF NOT EXISTS poll_options(option_id integer NOT NULL, order_id integer NOT NULL, poll_id integer REFERENCES polls(poll_id), response TEXT NOT NULL, correct bool NOT NULL)")
    database.execute("CREATE TABLE IF NOT EXISTS poll_responses(poll_id integer NOT NULL REFERENCES polls(poll_id), student_id TEXT NOT NULL REFERENCES users(user_id), option_id integer NOT NULL)")
    
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    # add boolean to course config
    config_file = Path(course_dir, 'config', 'config.json')
    if config_file.is_file():
        with open(config_file, 'r') as in_file:
            j = json.load(in_file)
            j['course_details']['polls_enabled'] = False
            j['course_details']['polls_pts_for_correct'] = 1.0
            j['course_details']['polls_pts_for_incorrect'] = 0.0

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
    database.execute("DROP TABLE IF EXISTS poll_options")
    database.execute("DROP TABLE IF EXISTS poll_responses")
    database.execute("DROP TABLE IF EXISTS polls")