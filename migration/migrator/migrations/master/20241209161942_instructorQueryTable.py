"""Migration for the Submitty master database."""


def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    # Create overall comment table
    database.execute(
        """
       CREATE TABLE IF NOT EXISTS instructor_queries (
            user_id character varying NOT NULL,
            query_name character varying NOT NULL,
            query character varying NOT NULL
        )
        """
    )

    pass


def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    database.execute(
        """
        DROP TABLE instructor_queries;
        """
    )
    pass
