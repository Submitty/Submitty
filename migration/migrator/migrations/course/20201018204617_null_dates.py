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
    database.execute("ALTER TABLE ONLY electronic_gradeable ALTER COLUMN eg_submission_due_date DROP NOT NULL")
    database.execute("ALTER TABLE ONLY gradeable ALTER COLUMN g_grade_released_date DROP NOT NULL")


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
    database.execute("UPDATE electronic_gradeable SET eg_submission_due_date = '9997-01-01 04:59:59.000000' WHERE eg_submission_due_date is NULL;")
    database.execute("UPDATE gradeable SET g_grade_released_date = '9997-01-01 04:59:59.000000' WHERE g_grade_released_date is NULL;")
