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
        ALTER TABLE courses
            RENAME COLUMN semester TO term;
        -- Rename Columns
        ALTER TABLE courses_registration_sections
            RENAME COLUMN semester TO term;
        ALTER TABLE courses_users
            RENAME COLUMN semester TO term;
        ALTER TABLE emails
            RENAME COLUMN semester TO term;
        ALTER TABLE mapped_courses
            RENAME COLUMN semester TO term;
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
        ALTER TABLE courses
            RENAME COLUMN term TO semester;
        -- Rename Columns
        ALTER TABLE courses_registration_sections
            RENAME COLUMN term TO semester;
        ALTER TABLE courses_users
            RENAME COLUMN term TO semester;
        ALTER TABLE emails
            RENAME COLUMN term TO semester;
        ALTER TABLE mapped_courses
            RENAME COLUMN term TO semester;
    """)