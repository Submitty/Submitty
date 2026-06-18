import json
from pathlib import Path


def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    tgt = Path(config.submitty['submitty_install_dir'], 'config', 'database.json')

    with tgt.open() as infile:
        data = json.load(infile)

    data['database_port'] = 5432

    with tgt.open('w') as outfile:
        json.dump(data, outfile, indent=4)
