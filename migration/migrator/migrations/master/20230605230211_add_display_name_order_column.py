"""Migration for the Submitty master database."""


def up(config, database):
    """
    Run up migration.
    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    # add column
    sql = "ALTER TABLE users ADD COLUMN IF NOT EXISTS display_name_order character varying(255) NOT NULL DEFAULT 'GIVEN_F';"
    database.execute(sql)
    pass


def down(config, database):
    """
    Run down migration (rollback).
    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    # update user trigger
    pass