"""Migration for the Submitty master database."""
user_id_requirements = {
    "all": True,
    "require_name": False,
    "length": 25,
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

accepted_emails = {
    "gmail.com": True,
    "rpi.edu": True
}


import json
def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    # if 'user_create_account' not in config.submitty:
    submitty_config = config.submitty
    submitty_config['user_create_account'] = False
    submitty_config['user_id_requirements'] = user_id_requirements
    submitty_config['accepted_emails'] = accepted_emails
    
    dump = open(config.config_path / 'submitty.json', 'w')
    json.dump(submitty_config, dump, indent=4)

def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    pass
