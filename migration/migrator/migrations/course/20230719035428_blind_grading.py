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
    # Modify table structure
    sql_alter_table = """
    ALTER TABLE electronic_gradeable
    ALTER COLUMN eg_limited_access_blind SET DEFAULT 1,
    ADD COLUMN IF NOT EXISTS eg_instructor_blind integer DEFAULT 1;
    """
    database.execute(sql_alter_table)


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
