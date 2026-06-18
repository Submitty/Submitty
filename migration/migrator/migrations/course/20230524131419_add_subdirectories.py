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
    
    # Rename eg_subdirectory to eg_vcs_partial_path, and
    # create a new column for subdirectories, defaulted to '' and not nullable.
    database.execute("""
        ALTER TABLE electronic_gradeable
        RENAME COLUMN eg_subdirectory TO eg_vcs_partial_path;
        ALTER TABLE electronic_gradeable
        ADD COLUMN IF NOT EXISTS eg_vcs_subdirectory varchar(1024) DEFAULT '' NOT NULL;
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

    # Remove the subdirectory column,
    # and reset the eg_vcs_partial_path column to be eg_subdirectory
    database.execute("""
        ALTER TABLE electronic_gradeable
        DROP COLUMN IF EXISTS eg_vcs_subdirectory;
        ALTER TABLE electronic_gradeable
        RENAME COLUMN eg_vcs_partial_path TO eg_subdirectory;
    """)
