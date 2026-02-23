"""Migration for the Submitty system."""


import json
import os


def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    submitty_json_path = os.path.join(config.config_path, 'submitty.json')

    with open(submitty_json_path, 'r') as f:
        submitty_config = json.load(f)

    if 'password_requirements' not in submitty_config:
        submitty_config['password_requirements'] = {
            "min_length": 12,
            "max_length": 255,
            "require_uppercase": True,
            "require_lowercase": True,
            "require_numbers": True,
            "require_special_chars": True
        }

        with open(submitty_json_path, 'w') as f:
            json.dump(submitty_config, f, indent=4)


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
