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
        ADD COLUMN IF NOT EXISTS custom_answers BOOLEAN DEFAULT false;

        CREATE TABLE IF NOT EXISTS poll_options_custom (
            order_id integer NOT NULL DEFAULT 1,
            poll_id integer,
            response text NOT NULL,
            credit boolean NOT NULL,
            option_id integer NOT NULL,
            author_id VARCHAR(255)
        );

        CREATE SEQUENCE IF NOT EXISTS poll_options_custom_seq
            AS integer
            START WITH 1
            INCREMENT BY 1
            NO MINVALUE
            NO MAXVALUE
            CACHE 1;

        ALTER SEQUENCE poll_options_custom_seq OWNED BY poll_options_custom.order_id;
        ALTER TABLE poll_options_custom ALTER COLUMN order_id SET DEFAULT nextval('poll_options_custom_seq');
        ALTER TABLE poll_options_custom ALTER COLUMN option_id SET DEFAULT nextval('poll_options_custom_seq');
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
