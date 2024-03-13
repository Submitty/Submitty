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
    database.execute("ALTER TABLE polls ADD COLUMN IF NOT EXISTS duration BIGINT DEFAULT 0")
    database.execute("ALTER TABLE polls ADD COLUMN IF NOT EXISTS end_date timestamptz NOT NULL DEFAULT '1900-02-01'")
    database.execute("ALTER TABLE polls ALTER COLUMN status DROP NOT NULL")
    database.execute("""
        UPDATE polls SET end_date = CASE
            WHEN status = 'open' THEN '9999-02-01'
            WHEN status = 'closed' THEN '1900-02-01'
            ELSE now() END
    """)

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
    database.execute("ALTER TABLE polls DROP COLUMN IF EXISTS end_date")
    database.execute("ALTER TABLE polls DROP COLUMN IF EXISTS duration")
    
    
