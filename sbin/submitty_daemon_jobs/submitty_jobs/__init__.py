"""
init file.
"""

import os
import json
from pathlib import Path

CONFIG_PATH = os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', '..', '..', 'config')
print ("CONFIG PATH "+CONFIG_PATH)
os.system ("ls -lta "+str(CONFIG_PATH))
with open(Path(CONFIG_PATH, 'submitty.json'),'r') as open_file:
    JSON_FILE = json.load(open_file)
DATA_DIR = JSON_FILE['submitty_data_dir']
QUEUE_DIR = Path(DATA_DIR, 'submitty_daemon_job_queue')

with open(Path(CONFIG_PATH, 'submitty_users.json')) as open_file:
    JSON_FILE = json.load(open_file)
DAEMON_USER = JSON_FILE['daemon_user']
