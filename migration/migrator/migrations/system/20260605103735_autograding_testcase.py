"""Migration for the Submitty system."""


def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    database.execute(
        """
        CREATE TABLE IF NOT EXISTS autograding_testcase (
            g_id        character varying(255)  not NULL,
            testcase_id character varying(255)  not NULL,
            testcase_order integer              not NULL,
            hidden      boolean                 not NULL,
            extra_credit boolean                not NULL,
            points_possible numeric(10,2)      not NULL
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
