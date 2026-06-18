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
        CREATE TABLE IF NOT EXISTS forum_upducks (
            post_id integer NOT NULL,
            user_id VARCHAR(255) NOT NULL,
            FOREIGN KEY(post_id)
                REFERENCES posts(id)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            FOREIGN KEY(user_id)
                REFERENCES users(user_id)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            UNIQUE (user_id,post_id)
        );
    """
    )
    pass

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
    pass
