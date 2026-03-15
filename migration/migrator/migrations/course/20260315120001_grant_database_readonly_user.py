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
    database.execute("GRANT USAGE ON SCHEMA public TO database_readonly_user")
    database.execute("GRANT SELECT ON ALL TABLES IN SCHEMA public TO database_readonly_user")
    database.execute("GRANT SELECT ON ALL SEQUENCES IN SCHEMA public TO database_readonly_user")
    database.execute("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO database_readonly_user")
    database.execute("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON SEQUENCES TO database_readonly_user")


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
    # No rollback required for defaults here.
    pass
