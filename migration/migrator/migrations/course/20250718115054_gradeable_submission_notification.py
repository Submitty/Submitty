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
        ALTER TABLE notification_settings
        ADD COLUMN IF NOT EXISTS all_gradeable_releases BOOLEAN DEFAULT TRUE NOT NULL,
        ADD COLUMN IF NOT EXISTS all_gradeable_releases_email BOOLEAN DEFAULT FALSE NOT NULL;

        ALTER TABLE electronic_gradeable
        ADD COLUMN IF NOT EXISTS eg_submission_notification_sent BOOLEAN DEFAULT FALSE NOT NULL;

        UPDATE electronic_gradeable eg
        SET eg_submission_notification_sent = TRUE
        FROM gradeable g
        WHERE eg.g_id = g.g_id AND g.g_grade_released_date < NOW();
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
