"""Migration for a given Submitty course database."""


def up(config, database, term, course):
    """
    Run up migration.
    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    :param term: term of the course being migrated
    :type term: str
    :param course: Code of course being migrated
    :type course: str
    """

    database.execute("ALTER TABLE queue ALTER COLUMN time_in TYPE timestamp with time zone;");
    database.execute("ALTER TABLE queue ALTER COLUMN time_help_start TYPE timestamp with time zone;");
    database.execute("ALTER TABLE queue ALTER COLUMN time_out TYPE timestamp with time zone;");


def down(config, database, term, course):
    """
    Run down migration (rollback).
    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    :param term: term of the course being migrated
    :type term: str
    :param course: Code of course being migrated
    :type course: str
    """
    
    database.execute("ALTER TABLE queue ALTER COLUMN time_in TYPE timestamp;");
    database.execute("ALTER TABLE queue ALTER COLUMN time_help_start TYPE timestamp;");
    database.execute("ALTER TABLE queue ALTER COLUMN time_out TYPE timestamp;");
