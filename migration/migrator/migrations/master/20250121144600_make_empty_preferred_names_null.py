"""Migration for the Submitty master database."""


def up(config, database):
    # Set all users with an empty string for user_preferred_givenname to NULL and add a constraint to prevent future empty strings
    database.execute("UPDATE users SET user_preferred_givenname = NULL WHERE user_preferred_givenname = ''")
    database.execute("ALTER TABLE users ADD CONSTRAINT user_preferred_givenname_not_empty CHECK (user_preferred_givenname <> '')")
    database.execute("ALTER TABLE users ADD CONSTRAINT user_preferred_familyname_not_empty CHECK (user_preferred_familyname <> '')")
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    pass


def down(config, database):
    # Remove the constraint that prevents empty strings in user_preferred_givenname
    database.execute("ALTER TABLE users DROP CONSTRAINT user_preferred_givenname_not_empty")
    database.execute("ALTER TABLE users DROP CONSTRAINT user_preferred_familyname_not_empty")
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    pass
