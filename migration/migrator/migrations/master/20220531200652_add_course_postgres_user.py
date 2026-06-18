"""Migration for the Submitty master database."""

import json
from collections import OrderedDict


def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    database_file = config.config_path / 'database.json'
    with(database_file).open('r') as db_file:
        db_info = json.load(db_file, object_pairs_hook=OrderedDict)
        if 'database_course_user' not in db_info or 'database_course_password' not in db_info:
            raise Exception('Make sure to read the SYSADMIN ACTION notes!')
        res = database.execute("SELECT oid FROM pg_authid WHERE rolname='{}'".format(db_info['database_course_user']))
        if res.rowcount == 0:
            database.execute("CREATE ROLE {} LOGIN PASSWORD '{}'".format(db_info['database_course_user'], db_info['database_course_password']))


def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    pass
