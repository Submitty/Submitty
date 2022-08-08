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
        ALTER TABLE sessions
        ADD COLUMN IF NOT EXISTS session_created timestamp with time zone DEFAULT current_timestamp,
        ADD COLUMN IF NOT EXISTS browser_name character varying(50),
        ADD COLUMN IF NOT EXISTS browser_version character varying(15),
        ADD COLUMN IF NOT EXISTS platform character varying(50);
    """)
    database.execute("""
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS enforce_secure_session boolean DEFAULT false;
    """)

def down(config, database):
    """
    Run down migration (rollback).
    """
    database.execute("""
        ALTER TABLE sessions
        DROP COLUMN IF EXISTS session_created,
        DROP COLUMN IF EXISTS browser_name,
        DROP COLUMN IF EXISTS browser_version,
        DROP COLUMN IF EXISTS platform;
    """)
    database.execute("""
        ALTER TABLE users
        DROP COLUMN IF EXISTS enforce_secure_session;
    """)
