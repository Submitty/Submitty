"""Migration for the Submitty master database."""


def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    database.execute("ALTER TABLE users ADD COLUMN IF NOT EXISTS user_email_secondary character varying(255) NOT NULL DEFAULT '';")
    database.execute("ALTER TABLE users ADD COLUMN IF NOT EXISTS user_email_secondary_notify boolean DEFAULT false;")
    database.execute("ALTER TABLE emails ADD COLUMN IF NOT EXISTS email_address character varying(255) NOT NULL DEFAULT '';")

def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    pass
