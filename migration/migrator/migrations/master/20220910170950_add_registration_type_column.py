"""Migration for the Submitty master database."""


def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    database.execute("""
ALTER TABLE public.courses_users
ADD registration_type character varying(255) DEFAULT 'graded':character varying NOT NULL
ADD CONSTRAINT check_registration_type CHECK (((registration_type)::text = ANY (ARRAY[('graded'::character varying)::text, ('audit'::character varying)::text, ('withdrawn'::character varying)::text, ('staff'::character varying)::text])));
""")


def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    database.execute("""
ALTER TABLE public.courses_users
DROP COLUMN registration_type;
""")
