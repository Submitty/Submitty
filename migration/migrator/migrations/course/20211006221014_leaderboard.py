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
            user_id character varying(255),
            team_id character varying(255),
            g_id character varying(255) NOT NULL,
            testcase_id character varying(255) NOT NULL,
            time real NOT NULL,
            memory integer NOT NULL,
            passed boolean NOT NULL,
            primary key (user_id, team_id, g_id, testcase_id),
            CONSTRAINT metrics_user_team_id_check CHECK (((user_id IS NOT NULL) OR (team_id IS NOT NULL))),
            constraint time_nonnegative check (time >= 0),
            constraint memory_nonnegative check (memory >= 0)
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
