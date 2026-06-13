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
    # If role doesn't exist, create a fixed readonly user
    res = database.execute("SELECT oid FROM pg_authid WHERE rolname='database_readonly_user'")
    if res.rowcount == 0:
        database.execute("CREATE ROLE database_readonly_user LOGIN PASSWORD 'database_readonly_user' NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION")


def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    # keep for safety; we do not drop role by default
    pass
