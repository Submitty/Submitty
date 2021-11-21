import os
from pathlib import Path

"""Migration for the Submitty system."""


def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    os.system("chgrp -R {} {}".format(config.submitty_users['daemonphp_group'], str(Path(config.submitty['submitty_data_dir'], 'logs', 'autograding_stack_traces'))))
    os.system("chgrp -R {} {}".format(config.submitty_users['daemonphp_group'], str(Path(config.submitty['submitty_data_dir'], 'logs', 'autograding'))))


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    os.system("chgrp -R {} {}".format(config.submitty_users['course_builders_group'], str(Path(config.submitty['submitty_data_dir'], 'logs', 'autograding_stack_traces'))))
    os.system("chgrp -R {} {}".format(config.submitty_users['course_builders_group'], str(Path(config.submitty['submitty_data_dir'], 'logs', 'autograding'))))
