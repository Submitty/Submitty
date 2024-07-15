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
    database.execute(
        """
        ALTER TABLE polls
        ADD COLUMN IF NOT EXISTS allows_custom boolean NOT NULL DEFAULT false;

        ALTER TABLE poll_options
        ADD COLUMN IF NOT EXISTS author_id VARCHAR(255) DEFAULT NULL;

        ALTER TABLE poll_options
        DROP CONSTRAINT IF EXISTS poll_options_fkey;

        ALTER TABLE poll_options
        ADD CONSTRAINT poll_options_fkey
        FOREIGN KEY (author_id) REFERENCES users(user_id) ON UPDATE CASCADE;
        """
    )


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
    pass
