"""
init file.
"""

import json
from pathlib import Path

CONFIG_PATH = Path('.', '..', '..', '..', 'config')
with open(Path(CONFIG_PATH, 'submitty.json')) as open_file:
    JSON_FILE = json.load(open_file)
DATA_DIR = JSON_FILE['submitty_data_dir']
QUEUE_DIR = Path(DATA_DIR, 'submitty_daemon_job_queue')

with open(Path(CONFIG_PATH, 'submitty_users.json')) as open_file:
    JSON_FILE = json.load(open_file)
DAEMON_USER = JSON_FILE['daemon_user']
