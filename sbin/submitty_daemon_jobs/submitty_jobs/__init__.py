"""
init file.
"""

import os
import json
from pathlib import Path

CONFIG_PATH = Path(__file__).resolve().parents[3] / 'config'

try:
    with open(str(Path(CONFIG_PATH, 'submitty.json'))) as open_file:
        JSON_FILE = json.load(open_file)

    INSTALL_DIR = JSON_FILE['submitty_install_dir']
    DATA_DIR = JSON_FILE['submitty_data_dir']
    QUEUE_DIR = Path(DATA_DIR, 'daemon_job_queue')
except Exception as err:
    if os.environ.get('PYTEST') is None:
        raise err
    else:
        # Provide fallback values for testing
        INSTALL_DIR = '/usr/local/submitty'
        DATA_DIR = '/var/local/submitty'
        QUEUE_DIR = Path(DATA_DIR, 'daemon_job_queue')


try:
    with open(str(Path(CONFIG_PATH, 'submitty_users.json'))) as open_file:
        JSON_FILE = json.load(open_file)
    DAEMON_USER = JSON_FILE['daemon_user']
    VERIFIED_ADMIN_USER = JSON_FILE['verified_submitty_admin_user']
except Exception as err:
    if os.environ.get('PYTEST') is None:
        raise err
    else:
        # Provide fallback values for testing
        DAEMON_USER = 'submitty_daemon'
        VERIFIED_ADMIN_USER = 'submitty_admin'
