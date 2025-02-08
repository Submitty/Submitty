"""Migration for the Submitty master database."""


def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """

    database.execute('''
        CREATE TABLE if not exists public.unverified_users (
            user_id character varying NOT NULL,
            user_givenname character varying NOT NULL,
            user_password character varying,
            user_familyname character varying NOT NULL,
            user_email character varying NOT NULL,
            verification_code character varying(50) NOT NULL DEFAULT 'none',
            verification_expiration timestamptz DEFAULT NOW()
        )
    ''')

def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    pass
