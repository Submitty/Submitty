"""
init file.
"""

import os
import json
from pathlib import Path

CONFIG_PATH = os.path.join(os.path.dirname(
    os.path.realpath(__file__)),
    '..',
    '..',
    '..',
    'config'
)

with open(str(Path(CONFIG_PATH, 'submitty.json'))) as open_file:
    JSON_FILE = json.load(open_file)
INSTALL_DIR = JSON_FILE['submitty_install_dir']
DATA_DIR = JSON_FILE['submitty_data_dir']
QUEUE_DIR = Path(DATA_DIR, 'daemon_job_queue')

with open(str(Path(CONFIG_PATH, 'submitty_users.json'))) as open_file:
    JSON_FILE = json.load(open_file)
DAEMON_USER = JSON_FILE['daemon_user']
