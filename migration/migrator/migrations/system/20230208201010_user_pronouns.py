"""Migration for the Submitty system."""


def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    
    database.execute("""
ALTER TABLE IF EXISTS users 
ADD COLUMN IF NOT EXISTS user_pronouns character varying;
    $$ LANGUAGE plpgsql;""")
    pass


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
