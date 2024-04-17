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
    database.execute("ALTER TABLE polls ADD COLUMN IF NOT EXISTS duration INTEGER DEFAULT 0")
    database.execute("ALTER TABLE polls ADD COLUMN IF NOT EXISTS end_time timestamptz DEFAULT NULL")
    database.execute("ALTER TABLE polls ALTER COLUMN status DROP NOT NULL")
    database.execute("ALTER TABLE polls ADD COLUMN IF NOT EXISTS is_visible BOOLEAN NOT NULL DEFAULT FALSE")
    database.execute("""
        UPDATE polls SET 
            end_time = CASE
                WHEN status = 'open' THEN NULL
                ELSE now() 
            END,
            is_visible = CASE
                WHEN status IN ('open', 'ended') THEN TRUE
                ELSE FALSE
            END
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
    database.execute("ALTER TABLE polls DROP COLUMN IF EXISTS end_time")
    
    
