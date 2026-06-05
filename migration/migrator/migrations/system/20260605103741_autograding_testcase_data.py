"""Migration for the Submitty system."""


def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    database.execute(
        """
        CREATE TABLE IF NOT EXISTS autograding_testcase_data (
            g_id        character varying(255)      not NULL,
            testcase_order integer                  not NULL,
            user_id     character varying(255),
            team_id     character varying(255),
            g_version   integer                     not NULL,
            points_earned numeric(10,0)             not NULL,
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
