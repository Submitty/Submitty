"""Migration for the Submitty system."""
import os

def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    daemonphpcgi_group = config.submitty_users['daemonphpcgi_group']
    vcs_path = os.path.join(config.submitty['submitty_data_dir'], 'vcs', 'git')
    os.system(f"chgrp -R {daemonphpcgi_group} {vcs_path}")

    cgi_user = config.submitty_users['cgi_user']
    os.system(f"chown -R {cgi_user} {vcs_path}")


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    vcs_path = os.path.join(config.submitty['submitty_data_dir'], 'vcs', 'git')
    os.system(f"chown -R root {vcs_path}")
