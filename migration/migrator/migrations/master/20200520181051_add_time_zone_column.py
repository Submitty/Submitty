"""Migration for the Submitty master database."""


def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    default_time_zone = config.submitty['timezone']
    sql = "ALTER TABLE users ADD COLUMN time_zone VARCHAR NOT NULL DEFAULT '" + default_time_zone + "';"
    database.execute(sql)


def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    sql = "ALTER TABLE users DROP COLUMN IF EXISTS time_zone;"
    database.execute(sql)
