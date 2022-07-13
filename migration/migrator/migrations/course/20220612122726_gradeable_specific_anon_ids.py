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
    database.execute("CREATE TABLE IF NOT EXISTS gradeable_anon (user_id character varying NOT NULL, g_id character varying(255) NOT NULL, anon_id character varying(255))")
    database.execute("INSERT INTO gradeable_anon (SELECT u.user_id, g_id, u.anon_id FROM gradeable g JOIN users u ON 1=1 WHERE NOT EXISTS (SELECT 1 FROM gradeable_anon WHERE user_id=u.user_id AND g_id=g.g_id))")

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
    database.execute('DROP TABLE IF EXISTS gradeable_anon;')
