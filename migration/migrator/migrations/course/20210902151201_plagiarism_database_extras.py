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

    # Move the last run timestamp to the database
    database.execute("ALTER TABLE lichen ADD COLUMN IF NOT EXISTS last_run_timestamp TIMESTAMP WITH TIME ZONE DEFAULT NOW();")

    # Move the provided code data to the database
    database.execute("ALTER TABLE lichen ADD COLUMN IF NOT EXISTS has_provided_code BOOLEAN NOT NULL DEFAULT FALSE;")

    # create table to hold view timestamps
    database.execute(
        """
        CREATE TABLE IF NOT EXISTS lichen_run_access (
            id SERIAL PRIMARY KEY,
            lichen_run_id INT NOT NULL,
            user_id VARCHAR(255) NOT NULL,
            timestamp TIMESTAMP WITH TIME ZONE NOT NULL,
            CONSTRAINT fk_lichen_run_id
                FOREIGN KEY(lichen_run_id)
                    REFERENCES lichen(id)
                    ON DELETE CASCADE
        );
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
