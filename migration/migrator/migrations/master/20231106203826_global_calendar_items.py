"""Migration for the Submitty master database."""


def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    # Create the sequence for global_calendar_items id
    # Create the table for global_calendar_items
    # Set the OWNED BY property for the sequence
    database.execute(
    """
    CREATE SEQUENCE IF NOT EXISTS global_calendar_items_id_seq
        AS integer
        START WITH 1
        INCREMENT BY 1
        NO MINVALUE
        NO MAXVALUE
        CACHE 1;

    CREATE TABLE IF NOT EXISTS global_calendar_items (
        id integer NOT NULL DEFAULT nextval('global_calendar_items_id_seq'::regclass),
        type integer NOT NULL,
        text character varying(255) NOT NULL,
        date date NOT NULL
    );

    ALTER SEQUENCE global_calendar_items_id_seq OWNED BY global_calendar_items.id;
    """
    )


def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    pass
