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
        ALTER TABLE users ADD COLUMN IF NOT EXISTS is_verified boolean default true,
                    ADD COLUMN IF NOT EXISTS verification_code VARCHAR(50) default '',
                    ADD COLUMN IF NOT EXISTS verification_expiration TIMESTAMP default CURRENT_TIMESTAMP;
    ''')

    database.execute('''
        CREATE TABLE if not exists public.unverified_users (
    user_id character varying NOT NULL,
    user_numeric_id character varying,
    user_givenname character varying NOT NULL,
    user_preferred_givenname character varying,
    user_password character varying,
    user_familyname character varying NOT NULL,
    user_email_secondary character varying(255) DEFAULT ''::character varying NOT NULL,
    user_email_secondary_notify boolean DEFAULT false,
    user_pronouns character varying(255) DEFAULT ''::character varying,
    user_preferred_familyname character varying,
    user_email character varying NOT NULL,
    verification_code character varying(50) NOT NULL DEFAULT 'none',
    verification_expiration timestamp DEFAULT current_timestamp
    )
    ''')
    pass


def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    pass
