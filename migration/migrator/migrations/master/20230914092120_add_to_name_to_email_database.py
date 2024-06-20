"""Migration for the Submitty master database."""


def up(config, database):
    """
    Run up migration.
    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    database.execute("""
        ALTER TABLE emails
        ADD COLUMN IF NOT EXISTS to_name VARCHAR;
        --- Now user_id null if to_name used instead.
        ALTER TABLE emails
        ALTER COLUMN user_id DROP NOT NULL;

        ALTER TABLE emails
        DROP CONSTRAINT IF EXISTS name_or_email;
        ALTER TABLE emails
        ADD CONSTRAINT name_or_email CHECK (
            (user_id is NOT NULL)
            <>
            (to_name is NOT NULL)
        );
    """)

def down(config, database):
    """
    Run down migration (rollback).
    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    pass