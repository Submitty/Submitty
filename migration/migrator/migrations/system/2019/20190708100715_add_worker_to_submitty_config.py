"""
Migration for the Submitty system.
Adds bool to specify if the machine is a worker to Submitty.config
"""
from pathlib import Path
import json
import os


def up(config):
    submitty_json = str(Path(config.submitty['submitty_install_dir'], 'config', 'submitty.json'))
    submitty_conf = str(Path(config.submitty['submitty_install_dir'], '.setup', 'submitty_conf.json'))

    with open(submitty_conf, 'r') as infile:
        conf_data = json.load(infile)
        is_worker = conf_data['worker']

    with open(submitty_json, 'r') as infile:
        data = json.load(infile)

    data['worker'] = True if is_worker == 1 else False

    with open(submitty_json, 'w') as outfile:
        json.dump(data, outfile, indent=4)

# no need for down as email_enabled is not used in previous builds
def down(config):
    pass
