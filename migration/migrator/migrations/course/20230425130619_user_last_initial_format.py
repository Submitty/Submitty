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
    
    database.execute("""
ALTER TABLE users ADD COLUMN IF NOT EXISTS user_last_initial_format integer DEFAULT 0 NOT NULL;
ALTER TABLE users DROP CONSTRAINT IF EXISTS users_user_last_initial_format_check;
ALTER TABLE users ADD CONSTRAINT users_user_last_initial_format_check CHECK (((user_last_initial_format >= 0) AND (user_last_initial_format <= 3)));
    """)


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

    database.execute("""
ALTER TABLE users DROP CONSTRAINT IF EXISTS users_user_last_initial_format_check;
ALTER TABLE users DROP COLUMN IF EXISTS user_last_initial_format;
    """)
