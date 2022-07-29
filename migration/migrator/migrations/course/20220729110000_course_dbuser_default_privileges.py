"""Migration for a given Submitty course database."""
import json
from collections import OrderedDict

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
    database_file = config.config_path / 'database.json'
    with(database_file).open('r') as db_file:
        db_info = json.load(db_file, object_pairs_hook=OrderedDict)
        database.execute("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO {}".format(db_info['database_course_user']))
        database.execute("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, UPDATE ON SEQUENCES TO {}".format(db_info['database_course_user']))


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
    database_file = config.config_path / 'database.json'
    with(database_file).open('r') as db_file:
        db_info = json.load(db_file, object_pairs_hook=OrderedDict)
        database.execute("ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE SELECT, INSERT, UPDATE, DELETE ON TABLES FROM {}".format(db_info['database_course_user']))
        database.execute("ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE SELECT, UPDATE ON SEQUENCES FROM {}".format(db_info['database_course_user']))
