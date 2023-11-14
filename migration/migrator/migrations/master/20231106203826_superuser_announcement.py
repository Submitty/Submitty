"""Migration for the Submitty master database."""


def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    # Create the sequence for superuser_announcements id
    database.execute(
    """
    CREATE SEQUENCE IF NOT EXISTS superuser_announcements_id_seq
        AS integer
        START WITH 1
        INCREMENT BY 1
        NO MINVALUE
        NO MAXVALUE
        CACHE 1;
    """
    )

    # Create the table for superuser_announcements
    database.execute(
    """
    CREATE TABLE IF NOT EXISTS superuser_announcements (
        id integer NOT NULL DEFAULT nextval('superuser_announcements_id_seq'::regclass),
        type integer NOT NULL,
        text character varying(255) NOT NULL,
        date date NOT NULL
    );
    """
    )

    # Set the OWNED BY property for the sequence
    database.execute(
    """
    ALTER SEQUENCE superuser_announcements_id_seq OWNED BY superuser_announcements.id;
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
