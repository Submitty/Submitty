"""Migration for the Submitty system."""
import os
import json
from pathlib import Path

def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """

    tgt = str(Path(config.submitty['submitty_install_dir'], 'config', 'submitty.json'))

    with open(tgt, 'r') as infile:
        data = json.load(infile)

    data['duck_special_effects'] = False

    with open(tgt, 'w') as outfile:
        json.dump(data, outfile, indent=4)



def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
