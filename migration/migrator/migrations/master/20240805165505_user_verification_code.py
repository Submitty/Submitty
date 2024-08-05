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
