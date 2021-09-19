"""Migration for the Submitty master database."""
from collections import OrderedDict
import json


def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    with (config.config_path / 'authentication.json').open('w') as auth_file:
        json.dump({'authentication_method': config.database['authentication_method'], 'ldap_options': []}, auth_file, indent=2)
    del config.database.authentication_method
    with (config.config_path / 'database.json').open('r+') as db_file:
        db_info = json.load(db_file, object_pairs_hook=OrderedDict)
        db_file.seek(0)
        del db_info['authentication_method']
        json.dump(db_info, db_file, indent=2)


def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    auth_method = config.authentication['authentication_method']
    if auth_method == 'LdapAuthentication':
        auth_method = 'PamAuthentication'
    with (config.config_path / 'database.json').open('w') as db_file:
        db_info = json.load(db_file, object_pairs_hook=OrderedDict)
        db_file.seek(0)

        db_info['authentication_methd'] = auth_method
        json.dump(db_info, db_file, indent=2)
    (config.config_path / 'authentication.json').unlink()
