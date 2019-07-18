import json
import os
import shutil
import subprocess
import stat
import time
import traceback
from pwd import getpwnam
import glob

from submitty_utils import dateutils
from . import grade_item, grade_items_logging, CONFIG_PATH

with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
    OPEN_JSON = json.load(open_file)
    SUBMITTY_INSTALL_DIR = OPEN_JSON['submitty_install_dir']
    SUBMITTY_DATA_DIR = OPEN_JSON['submitty_data_dir']

with open(os.path.join(CONFIG_PATH, 'submitty_users.json')) as open_file:
    OPEN_JSON = json.load(open_file)
    DAEMON_UID = OPEN_JSON['daemon_uid']


def executeTestcases(complete_config_obj, tmp_logs, tmp_work, queue_obj, item_name, which_untrusted, job_id):

    is_batch_job = queue_obj["regrade"]
    job_id = queue_obj["job_id"]
    runner_success = -1



        
    return runner_success


