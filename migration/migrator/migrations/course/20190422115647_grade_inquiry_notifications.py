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
    #NOTE see other migrations for example usage. This is a workaround for executing a migration without a DB Session
    database.engine.execution_options(autocommit=True, isolation_level='AUTOCOMMIT').execute("ALTER type notifications_component ADD VALUE IF NOT EXISTS 'student';")
    database.engine.execution_options(autocommit=True, isolation_level='AUTOCOMMIT').execute("ALTER type notifications_component ADD VALUE IF NOT EXISTS 'grading';")


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
    database.engine.execute("DELETE FROM pg_enum WHERE enumlabel = 'student';")
    database.engine.execute("DELETE FROM pg_enum WHERE enumlabel = 'grading';")
