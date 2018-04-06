import json
from datetime import datetime
import os
from submitty_utils import dateutils
import fcntl


CONFIG_PATH = os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', '..', 'config')
with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
    OPEN_JSON = json.load(open_file)
AUTOGRADING_LOG_PATH = OPEN_JSON['autograding_log_path']
SUBMITTY_DATA_DIR = OPEN_JSON['submitty_data_dir']


def log_message(job_id="UNKNOWN", is_batch=False, which_untrusted="", jobname="", timelabel="", elapsed_time=-1,
                message=""):
    now = dateutils.get_current_time()
    datefile = datetime.strftime(now, "%Y%m%d")+".txt"
    autograding_log_file = os.path.join(AUTOGRADING_LOG_PATH, datefile)
    easy_to_read_date = dateutils.write_submitty_date(now, True)
    batch_string = "BATCH" if is_batch else ""
    if elapsed_time == "":
        elapsed_time = -1
    elapsed_time_string = "" if elapsed_time < 0 else '{:9.3f}'.format(elapsed_time)
    time_unit = "" if elapsed_time < 0 else "sec"
    with open(autograding_log_file, 'a') as myfile:
        fcntl.flock(myfile,fcntl.LOCK_EX | fcntl.LOCK_NB)
        print("%s | %6s | %5s | %11s | %-75s | %-6s %9s %3s | %s"
              % (easy_to_read_date, job_id, batch_string, which_untrusted,
                 jobname, timelabel, elapsed_time_string, time_unit, message),
              file=myfile)
        fcntl.flock(myfile, fcntl.LOCK_UN)
