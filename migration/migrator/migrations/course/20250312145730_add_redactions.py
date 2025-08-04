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
    database.execute("""
                     CREATE TABLE IF NOT EXISTS gradeable_redaction (
                        redaction_id SERIAL PRIMARY KEY,
                        g_id character varying(255) NOT NULL REFERENCES gradeable(g_id) ON DELETE CASCADE,
                        page integer NOT NULL,
                        x1 float NOT NULL CONSTRAINT x1_positive CHECK (x1 >= 0 AND x1 <= x2),
                        x2 float NOT NULL CONSTRAINT x2_positive CHECK (x2 >= 0 AND x2 <= 1),
                        y1 float NOT NULL CONSTRAINT y1_positive CHECK (y1 >= 0 AND y1 <= y2),
                        y2 float NOT NULL CONSTRAINT y2_positive CHECK (y2 >= 0 AND y2 <= 1)
                     )
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
    pass
