"""Migration for the Submitty master database."""

import json
from pathlib import Path
def up(config, database):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """

    my_file = Path("/usr/local/submitty/config/submitty.json")
    if my_file.is_file():
        with open('/usr/local/submitty/config/submitty.json', 'r') as conf:
            SUBMITTY_CONFIG_JSON = json.load(conf)
        if 'user_create_account' not in SUBMITTY_CONFIG_JSON:
            SUBMITTY_CONFIG_JSON['user_create_account'] = False
        
        dump = open('/usr/local/submitty/config/submitty.json', 'w')
        json.dump(SUBMITTY_CONFIG_JSON, dump, indent=4)


def down(config, database):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    """
    pass
