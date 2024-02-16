"""Migration for the Submitty system."""
import os
import json

def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    php_user = config.submitty_users['php_user']
    daemon_user = config.submitty_users['daemon_user']
    cgi_user = config.submitty_users['cgi_user']

    os.system("addgroup submitty_daemonphpcgi")

    os.system(f"usermod -a -G submitty_daemonphpcgi {daemon_user}")
    os.system(f"usermod -a -G submitty_daemonphpcgi {php_user}")
    os.system(f"usermod -a -G submitty_daemonphpcgi {cgi_user}")

    vcs_path = os.path.join(config.submitty['submitty_data_dir'], 'vcs')
    os.system(f"chgrp -R submitty_daemonphpcgi {vcs_path}")

    submitty_users = config.submitty_users
    submitty_users['daemonphpcgi_group'] = "submitty_daemonphpcgi"
    submitty_users_file = config.config_path / 'submitty_users.json'

    with open(submitty_users_file, 'w') as json_file:
        json.dump(submitty_users, json_file, indent=2)


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    daemoncgi_group = config.submitty_users['daemoncgi_group']

    vcs_path = os.path.join(config.submitty['submitty_data_dir'], 'vcs')
    os.system(f"chgrp -R {daemoncgi_group} {vcs_path}")
