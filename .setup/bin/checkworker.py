import json
import os
import sys


CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
CONF_DIR = os.path.abspath(CURRENT_DIR + "../../../config")


# a python version of `
# CURRENT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )";
# CONF_DIR="${CURRENT_DIR}/../../../config";
# WORKER=$([[ $(jq -r '.worker' ${CONF_DIR}/submitty.json) == "true" ]] && echo 1 || echo 0) || 0`

def is_worker():
    worker = 0
    if os.environ['WORKER'] and not (os.environ['WORKER'] == 0):
        return 1

    try:
        # Read the JSON file from CONF_DIR
        json_file_path = os.path.join(CONF_DIR, 'submitty.json')
        with open(json_file_path, 'r') as f:
            config_data = json.load(f)

        # Check if 'worker' key exists and if its value is "true"
        if config_data.get('worker') == "true":
            worker = 1

    except (FileNotFoundError, json.JSONDecodeError) as e:
        print(e, file=sys.stderr)
        worker = 0

    return worker
