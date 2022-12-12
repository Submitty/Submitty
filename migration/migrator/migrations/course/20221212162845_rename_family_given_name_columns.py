"""Migration for a given Submitty course database."""


def up(config, database, semester, course):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    :param semester: Semester of the course being migrated
    :type semester: str
    :param course: Code of course being migrated
    :type course: str
    """
    database.execute("ALTER TABLE users RENAME COLUMN user_firstname TO user_givenname;")
    database.execute("ALTER TABLE users RENAME COLUMN user_preferred_firstname TO user_preferred_givenname;")
    database.execute("ALTER TABLE users RENAME COLUMN user_lastname TO user_familyname;")
    database.execute("ALTER TABLE users RENAME COLUMN user_preferred_lastname TO user_preferred_familyname;")

    pass


def down(config, database, semester, course):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    :param semester: Semester of the course being migrated
    :type semester: str
    :param course: Code of course being migrated
    :type course: str
    """
    database.execute("ALTER TABLE users RENAME COLUMN user_givenname TO user_firstname;")
    database.execute("ALTER TABLE users RENAME COLUMN user_preferred_givenname TO user_preferred_firstname;")
    database.execute("ALTER TABLE users RENAME COLUMN user_familyname TO user_lastname;")
    database.execute("ALTER TABLE users RENAME COLUMN user_preferred_familyname TO user_preferred_lastname;")

    pass
