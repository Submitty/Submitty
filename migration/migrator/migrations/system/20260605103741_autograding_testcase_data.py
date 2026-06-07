"""Migration for the Submitty system."""


def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    database.execute(
        """
        CREATE TABLE autograding_testcase_data (
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
    pass


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
