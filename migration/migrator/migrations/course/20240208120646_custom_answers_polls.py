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
    database.execute(
        """
        ALTER TABLE polls
        ADD COLUMN IF NOT EXISTS custom_answers boolean DEFAULT false;

        DROP TABLE IF EXISTS poll_options_custom;

        CREATE TABLE IF NOT EXISTS poll_options_custom (
            poll_id integer,
            response text NOT NULL,
            correct boolean NOT NULL,
            option_id integer NOT NULL DEFAULT 1,
            author_id VARCHAR(255) NOT NULL
        );

        CREATE SEQUENCE IF NOT EXISTS poll_options_order_id_seq
            AS integer
            START WITH 1
            INCREMENT BY 1
            NO MINVALUE
            NO MAXVALUE
            CACHE 1;

        ALTER SEQUENCE poll_options_custom_option_id_seq OWNED BY poll_options_custom.option_id;
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
