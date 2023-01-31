"""Migration for a given Submitty course database."""


def up(config, database, term, course):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    :param term: Semester of the course being migrated
    :type term: str
    :param course: Code of course being migrated
    :type course: str
    """
    database.execute("ALTER TABLE notification_settings ADD COLUMN IF NOT EXISTS merge_threads_email BOOLEAN DEFAULT FALSE NOT NULL")
    database.execute("ALTER TABLE notification_settings ADD COLUMN IF NOT EXISTS all_new_threads_email BOOLEAN DEFAULT FALSE NOT NULL")
    database.execute("ALTER TABLE notification_settings ADD COLUMN IF NOT EXISTS all_new_posts_email BOOLEAN DEFAULT FALSE NOT NULL")
    database.execute("ALTER TABLE notification_settings ADD COLUMN IF NOT EXISTS all_modifications_forum_email BOOLEAN DEFAULT FALSE NOT NULL")
    database.execute("ALTER TABLE notification_settings ADD COLUMN IF NOT EXISTS reply_in_post_thread_email BOOLEAN DEFAULT FALSE NOT NULL")


def down(config, database, term, course):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    :param term: Semester of the course being migrated
    :type term: str
    :param course: Code of course being migrated
    :type course: str
    """
    pass
