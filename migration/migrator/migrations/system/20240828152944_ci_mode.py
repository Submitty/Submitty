"""Migration for the Submitty system."""
import json

def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    edited_config = config.submitty
    if ('is_ci' not in edited_config):
        edited_config['is_ci'] = False
    
    with open(config.config_path / 'submitty.json', 'w') as file_path:
        json.dump(edited_config, file_path, indent=4)



def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
