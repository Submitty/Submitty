"""Migration for the Submitty system."""
from collections import OrderedDict
import json

def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    authentication_file = config.config_path / 'authentication.json'
    with authentication_file.open('r+') as auth_file:
        auth_info = json.load(auth_file, object_pairs_hook=OrderedDict)
        if not isinstance(auth_info['ldap_options'], dict):
            auth_info['ldap_options'] = {}
            auth_file.seek(0)
            auth_file.truncate()
            auth_file.write(json.dumps(auth_info, indent=4))


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
