from textwrap import dedent

"""Migration for the Submitty master database."""


def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    database.execute("ALTER TABLE courses_users ADD COLUMN IF NOT EXISTS date_registered TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW();")

    # The default value will fill in NOW() to all existing and historical records, which is a data inaccuracy.
    # We don't know the information history, so set all existing records' date_registration to NULL.
    database.execute("UPDATE courses_users SET date_registered=NULL;")

    # Trigger function has been updated in the post-migration hook.
    # q.v. migration/migrator/triggers/master/sync_courses_user.sql

def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    pass
