"""Migration for the Submitty system."""
import json
user_id_requirements = {
    "any_password": True,
    "require_name": False,
    "min_length": 6,
    "max_length": 25,
    "name_requirements": {
        "given_first": False,
        "given_name": 2,
        "family_name": 4
    },
    "require_email": False,
    "email_requirements": {
        "whole_email": False,
        "whole_prefix": False,
        "prefix_count": 6
    }
}

accepted_emails = [
    "gmail.com",
    "rpi.edu"
]

def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    edited_config = config.submitty
    if 'user_create_account' not in edited_config:
        edited_config['user_create_account'] = False
    if 'user_id_requirements' not in edited_config:
        edited_config['user_id_requirements'] = user_id_requirements
    if 'accepted_emails' not in edited_config:
        edited_config['accepted_emails'] = accepted_emails
    
    with open(config.config_path / 'submitty.json', 'w') as file_path:
        json.dump(edited_config, file_path, indent=4)

def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
