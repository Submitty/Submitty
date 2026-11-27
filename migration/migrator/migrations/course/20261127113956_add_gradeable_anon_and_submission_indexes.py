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
        "CREATE INDEX IF NOT EXISTS gradeable_anon_g_id_user_id_idx "
        "ON gradeable_anon (g_id, user_id)"
    )
    database.execute(
        "CREATE INDEX IF NOT EXISTS electronic_gradeable_data_user_id_idx "
        "ON electronic_gradeable_data (user_id)"
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
    database.execute("DROP INDEX IF EXISTS gradeable_anon_g_id_user_id_idx")
    database.execute("DROP INDEX IF EXISTS electronic_gradeable_data_user_id_idx")
