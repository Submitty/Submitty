"""Migration for a given Submitty course database."""


def up(config, database, semester, course):

    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    :param semester: Semester of the course being migrated
    :type semester: str
    :param course: Code of course being migrated
    :type course: str
    """

    database.execute(
    """
        CREATE TABLE IF NOT EXISTS block_user_action (
            id SERIAL PRIMARY KEY,
            user_id character varying(255) NOT NULL,
            action character varying(255) NOT NULL,
            expiration_date timestamp with time zone,
            created_by character varying(255) NOT NULL,
            created_at timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id)
                REFERENCES users(user_id)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            FOREIGN KEY (created_by)
                REFERENCES users(user_id)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT block_user_action_action_check
                CHECK (action IN ('no_forum_posts')),
            UNIQUE (user_id, action)
        );
    """
    )


def down(config, database, semester, course):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    :param semester: Semester of the course being migrated
    :type semester: str
    :param course: Code of course being migrated
    :type course: str
    """
    database.execute("DROP TABLE IF EXISTS block_user_action;")
