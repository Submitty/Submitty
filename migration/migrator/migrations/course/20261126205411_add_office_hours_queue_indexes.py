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
    # The queue keeps every entry for the whole term. Queue actions and a student's
    # status look entries up by user, every page refresh looks up the entries
    # currently in the queue, and the history and "helped today" counts filter
    # by time_in. Without these indexes each of those queries scans the table.
    database.execute("CREATE INDEX IF NOT EXISTS queue_user_id_idx ON queue (user_id)")
    database.execute(
        "CREATE INDEX IF NOT EXISTS queue_active_time_in_idx ON queue (time_in) "
        "WHERE current_state IN ('waiting', 'being_helped')"
    )
    database.execute("CREATE INDEX IF NOT EXISTS queue_time_in_idx ON queue (time_in)")


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
    database.execute("DROP INDEX IF EXISTS queue_user_id_idx")
    database.execute("DROP INDEX IF EXISTS queue_active_time_in_idx")
    database.execute("DROP INDEX IF EXISTS queue_time_in_idx")
