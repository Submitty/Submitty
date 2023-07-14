"""Migration for the Submitty system."""
from collections import OrderedDict
import json
from os import chmod
from shutil import chown


def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    authentication_file = config.config_path / 'authentication.json'
    if not authentication_file.exists():
        with authentication_file.open('w') as auth_file:
            json.dump({'authentication_method': config.database['authentication_method'], 'ldap_options': []}, auth_file, indent=4)
    else:
        with authentication_file.open('r+') as auth_file:
            auth_info = json.load(auth_file, object_pairs_hook=OrderedDict)
            auth_info['authentication_method'] = config.database['authentication_method']
            auth_file.seek(0)
            auth_file.truncate()
            auth_file.write(json.dumps(auth_info, indent=4))

    with (config.config_path / 'database.json').open('r+') as db_file:
        db_info = json.load(db_file, object_pairs_hook=OrderedDict)
        db_file.seek(0)
        db_file.truncate()
        del db_info['authentication_method']
        json.dump(db_info, db_file, indent=4)
    chown(authentication_file, 'root', config.submitty_users['daemonphp_group'])
    chmod(authentication_file, 0o440)


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """

    # Assume rollback may run as a "missing migration" and so config does not yet load authentication file
    with (config.config_path / 'authentication.json').open() as auth_file:
        authentication = json.load(auth_file)
    auth_method = authentication['authentication_method']
    if auth_method == 'LdapAuthentication':
        auth_method = 'PamAuthentication'
    with (config.config_path / 'database.json').open('r+') as db_file:
        db_info = json.load(db_file, object_pairs_hook=OrderedDict)
        db_file.seek(0)
        db_file.truncate()

        db_info['authentication_method'] = auth_method
        json.dump(db_info, db_file, indent=4)
