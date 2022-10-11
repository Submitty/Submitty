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
    database.execute("ALTER TABLE posts ALTER COLUMN timestamp TYPE timestamptz(0);")
    database.execute("ALTER TABLE forum_posts_history ALTER COLUMN edit_timestamp TYPE timestamptz(0);")
    database.execute("ALTER TABLE viewed_responses ALTER COLUMN timestamp TYPE timestamptz(0);")
    database.execute("ALTER TABLE solution_ta_notes ALTER COLUMN edited_at TYPE timestamptz(0);")
    database.execute("ALTER TABLE notifications ALTER COLUMN created_at TYPE timestamptz(0);")
    database.execute("ALTER TABLE notifications ALTER COLUMN seen_at TYPE timestamptz(0);")
    database.execute("ALTER TABLE regrade_discussion ALTER COLUMN timestamp TYPE timestamptz(0);")
    database.execute("ALTER TABLE regrade_requests ALTER COLUMN timestamp TYPE timestamptz(0);")


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
