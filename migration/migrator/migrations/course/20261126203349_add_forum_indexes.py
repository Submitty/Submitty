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
    # The forum's thread list and thread pages join posts to their thread, their
    # replies and their upducks; without these indexes each join scans the whole table.
    database.execute("CREATE INDEX IF NOT EXISTS posts_thread_id_idx ON posts (thread_id)")
    database.execute("CREATE INDEX IF NOT EXISTS posts_parent_id_idx ON posts (parent_id)")
    database.execute(
        "CREATE INDEX IF NOT EXISTS forum_upducks_post_id_idx ON forum_upducks (post_id)"
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
    database.execute("DROP INDEX IF EXISTS posts_thread_id_idx")
    database.execute("DROP INDEX IF EXISTS posts_parent_id_idx")
    database.execute("DROP INDEX IF EXISTS forum_upducks_post_id_idx")
