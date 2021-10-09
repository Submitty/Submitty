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
        CREATE TABLE IF NOT EXISTS autograding_metrics (
            user_id text,
            team_id text,
            g_id text NOT NULL,
            g_version integer NOT NULL,
            testcase_id text NOT NULL,
            elapsed_time real,
            max_rss_size integer,
            points integer NOT NULL,
            passed boolean NOT NULL,
            hidden boolean NOT NULL,
            primary key (user_id, team_id, g_id, testcase_id, g_version),
            CONSTRAINT metrics_user_team_id_check CHECK (((user_id IS NOT NULL) OR (team_id IS NOT NULL))),
            constraint elapsed_time_nonnegative check (elapsed_time >= 0),
            constraint max_rss_size_nonnegative check (max_rss_size >= 0)
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
    database.execute("DROP TABLE IF EXISTS autograding_metrics;")
