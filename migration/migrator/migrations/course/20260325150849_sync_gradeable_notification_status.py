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
        ALTER TABLE notifications
        ADD COLUMN IF NOT EXISTS gradeable_id VARCHAR(255) DEFAULT NULL
        """
    )
    database.execute(
        """
        CREATE INDEX IF NOT EXISTS notifications_user_gradeable_unseen_index
        ON notifications (to_user_id, gradeable_id)
        WHERE gradeable_id IS NOT NULL AND seen_at IS NULL
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
    database.execute(
        """
        DROP INDEX IF EXISTS notifications_user_gradeable_unseen_index
        """
    )
    database.execute(
        """
        ALTER TABLE notifications DROP COLUMN IF EXISTS gradeable_id
        """
    )
