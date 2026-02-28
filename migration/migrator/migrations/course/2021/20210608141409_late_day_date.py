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
    database.execute("""DELETE FROM late_days T1 USING late_days T2 WHERE T1.since_timestamp < T2.since_timestamp
    AND T1.user_id = T2.user_id AND T1.since_timestamp::date = T2.since_timestamp::date;""")
    database.execute("ALTER TABLE late_days ALTER COLUMN since_timestamp TYPE date USING since_timestamp::date;")


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
    database.execute("ALTER TABLE late_days ALTER COLUMN since_timestamp TYPE timestamptz USING since_timestamp::timestamptz;")
