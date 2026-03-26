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

    # Backfill gradeable_id for pre-existing gradeable notifications by matching
    # their content against the possible titles:
    #   grades_release:    "Grade Available for <title>"
    #   gradeable_release: "Submissions Open: <title> | Due ..."
    database.execute(
        """
        UPDATE notifications n
        SET gradeable_id = g.g_id
        FROM gradeable g
        WHERE n.gradeable_id IS NULL
          AND n.component = 'grading'
          AND (
            n.content LIKE 'Submissions Open: ' || g.g_title || ' |%'
            OR n.content = 'Grade Available for ' || g.g_title
          )
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
