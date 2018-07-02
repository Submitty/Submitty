"""
init file.
"""

import json
from pathlib import Path

CONFIG_PATH = Path('.', '..', '..', '..', 'config')
with open(Path(CONFIG_PATH, 'submitty.json')) as open_file:
    JSON_FILE = json.load(open_file)
DATA_DIR = JSON_FILE['submitty_data_dir']
QUEUE_DIR = Path(DATA_DIR, 'hwcron_job_queue')

with open(Path(CONFIG_PATH, 'submitty_users.json')) as open_file:
    JSON_FILE = json.load(open_file)
HWCRON_USER = JSON_FILE['hwcron_user']
