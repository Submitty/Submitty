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
    database.execute("ALTER TABLE lichen RENAME COLUMN sequence_length TO hash_size;")
    database.execute("ALTER TABLE lichen RENAME CONSTRAINT lichen_sequence_length_check TO lichen_hash_size_check;")


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
    database.execute("ALTER TABLE lichen RENAME COLUMN hash_size TO sequence_length;")
    database.execute("ALTER TABLE lichen RENAME CONSTRAINT lichen_hash_size_check TO lichen_sequence_length_check;")
