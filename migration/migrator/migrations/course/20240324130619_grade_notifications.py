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
            ALTER TABLE notification_settings ADD COLUMN IF NOT EXISTS all_released_grades BOOLEAN DEFAULT false NOT NULL;
            ALTER TABLE notification_settings ADD COLUMN IF NOT EXISTS all_released_grades_email BOOLEAN DEFAULT false NOT NULL;
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
            ALTER TABLE notification_settings DROP COLUMN all_released_grades;
            ALTER TABLE notification_settings DROP COLUMN all_released_grades_email;
        """
    )
