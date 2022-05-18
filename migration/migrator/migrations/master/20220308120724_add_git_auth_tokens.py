"""Migration for the Submitty master database."""


def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    database.execute(
    """
    CREATE TABLE IF NOT EXISTS vcs_auth_tokens (
        id serial NOT NULL PRIMARY KEY,
        user_id varchar NOT NULL,
        token varchar NOT NULL,
        name varchar NOT NULL,
        expiration timestamptz(0),
        CONSTRAINT user_fk
            FOREIGN KEY(user_id)
                REFERENCES users(user_id)
    )
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
