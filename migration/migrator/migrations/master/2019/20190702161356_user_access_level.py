"""Migration for the Submitty master database."""


def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    database.execute("ALTER TABLE IF EXISTS ONLY users ADD COLUMN IF NOT EXISTS user_access_level INTEGER NOT NULL DEFAULT 3;")
    database.execute("""
DO $$
BEGIN
    ALTER TABLE IF EXISTS ONLY users ADD CONSTRAINT users_user_access_level_check CHECK ((user_access_level >= 1) AND (user_access_level <= 3));
    EXCEPTION WHEN duplicate_object THEN RAISE NOTICE 'constraint users.users_user_access_level_check already exists, skipping.';
END;
$$ LANGUAGE plpgsql;""")


def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    pass
