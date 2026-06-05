"""Migration for the Submitty system."""


def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    database.execute(
        """
        CREATE TABLE autograding_testcase (
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
    pass


    def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
