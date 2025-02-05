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
    UPDATE users SET user_preferred_givenname = NULL 
    WHERE user_preferred_givenname = ''
    """)
    database.execute("""
    UPDATE users SET user_preferred_familyname = NULL 
    WHERE user_preferred_familyname = ''
    """)
    database.execute("""
    ALTER TABLE users ADD CONSTRAINT user_preferred_givenname_not_empty 
    CHECK (user_preferred_givenname <> '')
    """)
    database.execute("""
    ALTER TABLE users ADD CONSTRAINT user_preferred_familyname_not_empty 
    CHECK (user_preferred_familyname <> '')
    """)


def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    
    database.execute("""
    ALTER TABLE users DROP CONSTRAINT user_preferred_givenname_not_empty
    """)
    database.execute("""
    ALTER TABLE users DROP CONSTRAINT user_preferred_familyname_not_empty
    """)
    database.execute("""
    UPDATE users SET user_preferred_givenname = '' 
    WHERE user_preferred_givenname IS NULL
    """)
    database.execute("""
    UPDATE users SET user_preferred_familyname = '' 
    WHERE user_preferred_familyname IS NULL
    """)
