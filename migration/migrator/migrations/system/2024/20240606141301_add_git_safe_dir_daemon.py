"""Migration for the Submitty system."""
import os

def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    DAEMON_USER = config.submitty_users['daemon_user']
    RAINBOWGRADES_REPOSITORY = os.path.join(config.submitty['submitty_install_dir'], 'GIT_CHECKOUT','RainbowGrades')
    gitconfig_path = f"/home/{DAEMON_USER}/.gitconfig"
    gitconfig_content = """[safe]
        directory = {}
        directory = *""".format(RAINBOWGRADES_REPOSITORY)

    with open(gitconfig_path, 'w') as file:
        file.write(gitconfig_content)

def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    DAEMON_USER = config.submitty_users['daemon_user']
    RAINBOWGRADES_REPOSITORY = os.path.join(config.submitty['submitty_install_dir'], 'GIT_CHECKOUT','RainbowGrades')
    gitconfig_path = f"/home/{DAEMON_USER}/.gitconfig"
    gitconfig_content = """[safe]
        directory = {}""".format(RAINBOWGRADES_REPOSITORY)

    with open(gitconfig_path, 'w') as file:
        file.write(gitconfig_content)
