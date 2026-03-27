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
        CREATE TABLE IF NOT EXISTS full_autograding_testcase (
            g_id          character varying(255) NOT NULL,
            user_id       character varying(255),
            team_id       character varying(255),
            g_version     integer                NOT NULL,
            testcase_id   character varying(255) NOT NULL,
            testcase_order integer               NOT NULL,
            hidden        boolean                DEFAULT false NOT NULL,
            extra_credit  boolean                DEFAULT false NOT NULL,
            points_possible numeric(10,2)        NOT NULL,
            points_earned   numeric(10,2)        NOT NULL,
            CONSTRAINT user_team_id_check CHECK ((user_id IS NOT NULL) != (team_id IS NOT NULL))
            );
        """
        )
    database.execute(
        "CREATE INDEX idx_full_autograding_testcase_user ON full_autograding_testcase (g_id, user_id, g_version);"
    )
    database.execute(
        "CREATE INDEX idx_full_autograding_testcase_team ON full_autograding_testcase (g_id, team_id, g_version);"
    )
    database.execute(
        "CREATE INDEX idx_full_autograding_testcase_gradeable ON full_autograding_testcase (g_id, testcase_id);"
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
