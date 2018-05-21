#!/usr/bin/env python3

import os
import time
import signal
import shutil
import json
from submitty_utils import dateutils
import multiprocessing
import contextlib

from autograder import grade_items_logging
from autograder import grade_item

# ==================================================================================
CONFIG_PATH = os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', 'config')
with open(os.path.join(CONFIG_PATH, 'submitty_users.json')) as open_file:
    OPEN_JSON = json.load(open_file)
NUM_GRADING_SCHEDULER_WORKERS_string = OPEN_JSON['num_grading_scheduler_workers']
NUM_GRADING_SCHEDULER_WORKERS_int    = int(NUM_GRADING_SCHEDULER_WORKERS_string)
HWCRON_UID = OPEN_JSON['hwcron_uid']

with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
    OPEN_JSON = json.load(open_file)
SUBMITTY_INSTALL_DIR = OPEN_JSON['submitty_install_dir']
SUBMITTY_DATA_DIR = OPEN_JSON['submitty_data_dir']

JOB_ID = '~WORK~'


# ==================================================================================
# ==================================================================================
def worker_process(which_machine,address,which_untrusted,my_server):

    # verify the hwcron user is running this script
    if not int(os.getuid()) == int(HWCRON_UID):
        grade_items_logging.log_message(JOB_ID, message="ERROR: must be run by hwcron")
        raise SystemExit("ERROR: the grade_item.py script must be run by the hwcron user")

    # ignore keyboard interrupts in the worker processes
    signal.signal(signal.SIGINT, signal.SIG_IGN)
    counter = 0

    servername_workername = "{0}_{1}".format(my_server, address)
    autograding_zip = os.path.join(SUBMITTY_DATA_DIR,"autograding_TODO",servername_workername+"_"+which_untrusted+"_autograding.zip")
    submission_zip = os.path.join(SUBMITTY_DATA_DIR,"autograding_TODO",servername_workername+"_"+which_untrusted+"_submission.zip")
    todo_queue_file = os.path.join(SUBMITTY_DATA_DIR,"autograding_TODO",servername_workername+"_"+which_untrusted+"_queue.json")

    while True:
        if os.path.exists(todo_queue_file):
            try:
                results_zip_tmp = grade_item.grade_from_zip(autograding_zip,submission_zip,which_untrusted)
                results_zip = os.path.join(SUBMITTY_DATA_DIR,"autograding_DONE",servername_workername+"_"+which_untrusted+"_results.zip")
                done_queue_file = os.path.join(SUBMITTY_DATA_DIR,"autograding_DONE",servername_workername+"_"+which_untrusted+"_queue.json")
                #move doesn't inherit the permissions of the destination directory. Copyfile does.
                shutil.copyfile(results_zip_tmp, results_zip)

                os.remove(results_zip_tmp)
                with open(todo_queue_file, 'r') as infile:
                    queue_obj = json.load(infile)
                    queue_obj["done_time"]=dateutils.write_submitty_date(microseconds=True)
                with open(done_queue_file, 'w') as outfile:
                    json.dump(queue_obj, outfile, sort_keys=True, indent=4)        
            except Exception as e:
                grade_items_logging.log_message(JOB_ID, message="ERROR attempting to unzip graded item: " + which_machine + " " + which_untrusted + " exception " + repr(e))
                with contextlib.suppress(FileNotFoundError):
                    os.remove(autograding_zip)
                with contextlib.suppress(FileNotFoundError):
                    os.remove(submission_zip)
            with contextlib.suppress(FileNotFoundError):
                os.remove(todo_queue_file)
            counter = 0
        else:
            if counter >= 10:
                print (which_machine,which_untrusted,"wait")
                counter = 0
            counter += 1
            time.sleep(1)

                
# ==================================================================================
# ==================================================================================
def launch_workers(my_name, my_stats):
    num_workers = my_stats['num_autograding_workers']

    # verify the hwcron user is running this script
    if not int(os.getuid()) == int(HWCRON_UID):
        raise SystemExit("ERROR: the grade_item.py script must be run by the hwcron user")

    grade_items_logging.log_message(JOB_ID, message="grade_scheduler.py launched")

    # prepare a list of untrusted users to be used by the workers
    untrusted_users = multiprocessing.Queue()
    for i in range(num_workers):
        untrusted_users.put("untrusted" + str(i).zfill(2))

    # launch the worker threads
    address = my_stats['address']
    if address != 'localhost':
        which_machine="{0}@{1}".format(my_stats['username'], address)
    else:
        which_machine = address
    my_server=my_stats['server_name']
    processes = list()
    for i in range(0,num_workers):
        u = "untrusted" + str(i).zfill(2)
        p = multiprocessing.Process(target=worker_process,args=(which_machine,address,u,my_server))
        p.start()
        processes.append(p)

    # main monitoring loop
    try:
        while True:
            alive = 0
            for i in range(0,num_workers):
                if processes[i].is_alive:
                    alive = alive+1
                else:
                    grade_items_logging.log_message(JOB_ID, message="ERROR: process "+str(i)+" is not alive")
            if alive != num_workers:
                grade_items_logging.log_message(JOB_ID, message="ERROR: #workers="+str(num_workers)+" != #alive="+str(alive))
            #print ("workers= ",num_workers,"  alive=",alive)
            time.sleep(1)

    except KeyboardInterrupt:
        grade_items_logging.log_message(JOB_ID, message="grade_scheduler.py keyboard interrupt")

        # just kill everything in this group id right now
        # NOTE:  this may be a bug if the grandchildren have a different group id and not be killed
        os.kill(-os.getpid(), signal.SIGKILL)

        # run this to check if everything is dead
        #    ps  xao pid,ppid,pgid,sid,comm,user  | grep untrust

        # everything's dead, including the main process so the rest of this will be ignored
        # but this was mostly working...

        # terminate the jobs
        for i in range(0,num_workers):
            processes[i].terminate()

        # wait for them to join
        for i in range(0,num_workers):
            processes[i].join()

    grade_items_logging.log_message(JOB_ID, message="grade_scheduler.py terminated")
# ==================================================================================

def read_autograding_worker_json():
    all_workers_json   = os.path.join(SUBMITTY_DATA_DIR, "autograding_TODO", "autograding_worker.json")
    try:
        with open(all_workers_json, 'r') as infile:
            name_and_stats = json.load(infile)
            #grab the key and the value. NOTE: For now there should only ever be one pair.
            name = list(name_and_stats.keys())[0]
            stats = name_and_stats[name]
    except Exception as e:
        raise SystemExit("ERROR loading autograding_worker.json file: {0}".format(e))
    return name, stats
# ==================================================================================
if __name__ == "__main__":
    my_name, my_stats = read_autograding_worker_json()
    launch_workers(my_name, my_stats)
