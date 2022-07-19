"""Migration for the Submitty system."""
import os
import json
from collections import OrderedDict

def up(config):
    submitty_json = os.path.join(config.submitty['submitty_install_dir'], "config", "submitty.json")
    read_submitty_json = open(submitty_json, 'r')
    config = json.loads(read_submitty_json.read(), object_pairs_hook=OrderedDict)
    read_submitty_json.close()
    config['upgrade_linux_packages'] = False
    with open(submitty_json, 'w') as json_file:
        json.dump(config, json_file, indent=2)
def down(config):
    submitty_json = os.path.join(config.submitty['submitty_install_dir'], "config", "submitty.json")
    try:
        read_submitty_json = open(submitty_json, 'r')
        config = json.loads(read_submitty_json.read(), object_pairs_hook=OrderedDict)
        read_submitty_json.close()
        if 'upgrade_linux_packages' in config.keys():
            del config['upgrade_linux_packages']
        with open(submitty_json, 'w') as json_file:
            json.dump(config, json_file, indent=2)
    except:
        print("Some problem was encountered while modifying submitty.json")
