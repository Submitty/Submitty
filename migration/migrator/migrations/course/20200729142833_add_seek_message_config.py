"""Migration for a given Submitty course database."""

import configparser
import os
import json
from pathlib import Path

def up(config, conn, semester, course):
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    config_file = Path(course_dir, 'config', 'config.json')

    if config_file.is_file():
        f = open(config_file, 'r')
        config = json.load(f)

        if ('course_details' in config):
            config['course_details']['seek_message_enabled'] = False
            config['course_details']['seek_message_instructions'] = "Optionally, provide your local timezone, desired project topic, or other information that would be relevant to forming your team."
        f.close()

        f = open(config_file, 'w')
        json.dump(config, f, indent=2)
        f.close()

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