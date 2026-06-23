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
        CREATE TABLE IF NOT EXISTS autograding_testcase (
            id              serial                      PRIMARY KEY,
            g_id            character varying(255)      NOT NULL,
            testcase_id     character varying(255)      NOT NULL,
            testcase_order  integer                     NOT NULL,
            hidden          boolean                     NOT NULL,
            extra_credit    boolean                     NOT NULL,
            points_possible numeric(10,0)               NOT NULL
            );
        """
        )

    database.execute(
        """
        CREATE TABLE IF NOT EXISTS autograding_testcase_data (
            atd_id          integer                     NOT NULL,
            user_id         character varying(255),
            team_id         character varying(255),
            g_version       integer                     NOT NULL,
            points_earned   numeric(10,0)               NOT NULL,
            CONSTRAINT fk_testcase FOREIGN KEY (atd_id) REFERENCES autograding_testcase (id) ON DELETE CASCADE,
            CONSTRAINT user_team_id_check CHECK ((user_id IS NOT NULL) != (team_id IS NOT NULL))
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
    pass
