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
    CREATE TABLE IF NOT EXISTS saml_mapped_users (
        id serial NOT NULL PRIMARY KEY,
        saml_id varchar(255) NOT NULL,
        user_id varchar(255) NOT NULL,
        active boolean NOT NULL DEFAULT TRUE,
        CONSTRAINT fk_user_id
            FOREIGN KEY(user_id)
                REFERENCES users(user_id)
    );
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
