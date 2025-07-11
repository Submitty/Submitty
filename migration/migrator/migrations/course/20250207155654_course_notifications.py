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
            ADD COLUMN IF NOT EXISTS all_released_grades BOOLEAN DEFAULT TRUE NOT NULL,
            ADD COLUMN IF NOT EXISTS all_released_grades_email BOOLEAN DEFAULT TRUE NOT NULL;

            ALTER TABLE electronic_gradeable_version
            ADD COLUMN IF NOT EXISTS g_notification_sent BOOLEAN DEFAULT FALSE NOT NULL;

            UPDATE electronic_gradeable_version egv
            SET g_notification_sent = TRUE
            FROM gradeable g
            WHERE g.g_id = egv.g_id AND g.g_grade_released_date < NOW();
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
