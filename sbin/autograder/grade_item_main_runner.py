import configparser
import json
import os
import tempfile
import shutil
import subprocess
import stat
import time
import dateutil
import dateutil.parser
import urllib.parse
import string
import random
import socket
import zipfile
import traceback

from submitty_utils import dateutils, glob
from . import grade_item, grade_items_logging, write_grade_history, CONFIG_PATH

with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
    OPEN_JSON = json.load(open_file)
    SUBMITTY_INSTALL_DIR = OPEN_JSON['submitty_install_dir']
    SUBMITTY_DATA_DIR = OPEN_JSON['submitty_data_dir']

with open(os.path.join(CONFIG_PATH, 'submitty_users.json')) as open_file:
    OPEN_JSON = json.load(open_file)
    DAEMON_UID = OPEN_JSON['daemon_uid']


def executeTestcases(complete_config_obj, tmp_logs, tmp_work, queue_obj, submission_string, item_name, USE_DOCKER, container, which_untrusted):

    #The paths to the important folders.
    tmp_work_test_input = os.path.join(tmp_work, "test_input")
    tmp_work_subission = os.path.join(tmp_work, "submission")
    tmp_work_checkout = os.path.join(tmp_work, "checkout")

    queue_time_longstring = queue_obj["queue_time"]
    waittime = queue_obj["waittime"]
    is_batch_job = queue_obj["regrade"]
    job_id = queue_obj["job_id"]
    is_batch_job_string = "BATCH" if is_batch_job else "INTERACTIVE"
    runner_success = -1
    # run the run.out as the untrusted user
    with open(os.path.join(tmp_logs,"runner_log.txt"), 'w') as logfile:
        print ("LOGGING BEGIN my_runner.out",file=logfile)
        logfile.flush()
        testcases = complete_config_obj["testcases"]
        for testcase_num in range(len(testcases)):
            
            #make the tmp folder for this testcase.
            testcase_folder = os.path.join(tmp_work, "test{:02}".format(testcase_num))
            os.makedirs(testcase_folder)

            #Set the permissions 
            #TODO see if these can be made more strict. 
            # try:
            #   grade_item.add_permissions(testcase_folder, stat.S_IROTH | stat.S_IWOTH | stat.S_IXOTH)
            # except Exception as e:
            #   print(e)
            #Navigate into the newly created testcase folder.
            os.chdir(testcase_folder)

            try:
                if USE_DOCKER:
                    runner_success = subprocess.call(['docker', 'exec', '-w', tmp_work, container,
                                                      os.path.join(tmp_work, 'my_runner.out'), queue_obj['gradeable'],
                                                      queue_obj['who'], str(queue_obj['version']), submission_string, testcase_num], stdout=logfile)
                else:
                    print(os.path.join(SUBMITTY_INSTALL_DIR, "sbin", "untrusted_execute"),
                                                      which_untrusted,
                                                      os.path.join(tmp_work,"my_runner.out"),
                                                      queue_obj["gradeable"],
                                                      queue_obj["who"],
                                                      str(queue_obj["version"]),
                                                      submission_string,
                                                      str(testcase_num))
                    runner_success = subprocess.call([os.path.join(SUBMITTY_INSTALL_DIR, "sbin", "untrusted_execute"),
                                                      which_untrusted,
                                                      os.path.join(tmp_work,"my_runner.out"),
                                                      queue_obj["gradeable"],
                                                      queue_obj["who"],
                                                      str(queue_obj["version"]),
                                                      submission_string,
                                                      str(testcase_num)],
                                                      stdout=logfile)
                logfile.flush()
            except Exception as e:
                print ("ERROR caught runner.out exception={0}".format(str(e.args[0])).encode("utf-8"),file=logfile)
                logfile.flush()
            finally:
                #move back to the tmp_work directory (up one level)
                os.chdir(tmp_work)

        
        print ("LOGGING END my_runner.out",file=logfile)
        logfile.flush()


        # os.system('ls -al {0}'.format(tmp_work))
        # print('checkpoint (system exit)')
        # sys.exit(1)

        killall_success = subprocess.call([os.path.join(SUBMITTY_INSTALL_DIR, "sbin", "untrusted_execute"),
                                           which_untrusted,
                                           os.path.join(SUBMITTY_INSTALL_DIR, "sbin", "killall.py")],
                                          stdout=logfile)

        print ("KILLALL COMPLETE my_runner.out",file=logfile)
        logfile.flush()

        if killall_success != 0:
            msg='RUNNER ERROR: had to kill {} process(es)'.format(killall_success)
            print ("pid",os.getpid(),msg)
            grade_items_logging.log_message(job_id,is_batch_job,which_untrusted,item_name,"","",msg)
    return runner_success