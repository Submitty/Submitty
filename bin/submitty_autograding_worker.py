#!/usr/bin/env python3

import os
import sys
import time
import signal
import shutil
import json
import grade_items_logging
import grade_item
from submitty_utils import glob
from submitty_utils import dateutils
import multiprocessing
import contextlib
import socket

# ==================================================================================
# these variables will be replaced by INSTALL_SUBMITTY.sh
NUM_GRADING_SCHEDULER_WORKERS_string = "__INSTALL__FILLIN__NUM_GRADING_SCHEDULER_WORKERS__"
NUM_GRADING_SCHEDULER_WORKERS_int    = int(NUM_GRADING_SCHEDULER_WORKERS_string)

SUBMITTY_DATA_DIR = "__INSTALL__FILLIN__SUBMITTY_DATA_DIR__"
HWCRON_UID = "__INSTALL__FILLIN__HWCRON_UID__"

# ==================================================================================
# ==================================================================================
def worker_process(which_machine,which_untrusted):

    # verify the hwcron user is running this script
    if not int(os.getuid()) == int(HWCRON_UID):
        grade_items_logging.log_message(message="ERROR: must be run by hwcron")
        raise SystemExit("ERROR: the grade_item.py script must be run by the hwcron user")

    # ignore keyboard interrupts in the worker processes
    signal.signal(signal.SIGINT, signal.SIG_IGN)
    counter=0
    
    autograding_zip = os.path.join(SUBMITTY_DATA_DIR,"autograding_TODO",which_untrusted+"_autograding.zip")
    submission_zip = os.path.join(SUBMITTY_DATA_DIR,"autograding_TODO",which_untrusted+"_submission.zip")
    todo_queue_file = os.path.join(SUBMITTY_DATA_DIR,"autograding_TODO",which_untrusted+"_queue.json")

    while True:
        if os.path.exists(todo_queue_file):
            try:
                results_zip_tmp = grade_item.grade_from_zip(autograding_zip,submission_zip,which_untrusted)
                results_zip = os.path.join(SUBMITTY_DATA_DIR,"autograding_DONE",which_untrusted+"_results.zip")
                done_queue_file = os.path.join(SUBMITTY_DATA_DIR,"autograding_DONE",which_untrusted+"_queue.json")
                shutil.move(results_zip_tmp,results_zip)
                with open(todo_queue_file, 'r') as infile:
                    queue_obj = json.load(infile)
                    queue_obj["done_time"]=dateutils.write_submitty_date(microseconds=True)
                with open(done_queue_file, 'w') as outfile:
                    json.dump(queue_obj, outfile, sort_keys=True, indent=4)        
            except Exception as e:
                grade_items_logging.log_message(message="ERROR attempting to grade item: " + which_machine + " " + which_untrusted + " exception " + repr(e))
                grade_items_logging.log_message(jobname="DUNNO",message="ERROR: Exception when grading from zip")
                with contextlib.suppress(FileNotFoundError):
                    os.remove(autograding_zip)
                with contextlib.suppress(FileNotFoundError):
                    os.remove(submission_zip)
            with contextlib.suppress(FileNotFoundError):
                os.remove(todo_queue_file)
            counter = 0
        else:
            if counter == 0 or counter >= 10:
                print (which_machine,which_untrusted,"wait")
                counter=0
            counter+=1
            time.sleep(1)

                
# ==================================================================================
# ==================================================================================
def launch_workers(num_workers):

    # verify the hwcron user is running this script
    if not int(os.getuid()) == int(HWCRON_UID):
        raise SystemExit("ERROR: the grade_item.py script must be run by the hwcron user")

    grade_items_logging.log_message(message="grade_scheduler.py launched")

    # prepare a list of untrusted users to be used by the workers
    untrusted_users = multiprocessing.Queue()
    for i in range(num_workers):
        untrusted_users.put("untrusted" + str(i).zfill(2))

    # launch the worker threads
    which_machine=socket.gethostname()
    processes = list()
    for i in range(0,num_workers):
        u = "untrusted" + str(i).zfill(2)
        p = multiprocessing.Process(target=worker_process,args=(which_machine,u))
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
                    grade_items_logging.log_message(message="ERROR: process "+str(i)+" is not alive")
            if alive != num_workers:
                grade_items_logging.log_message(message="ERROR: #workers="+str(num_workers)+" != #alive="+str(alive))
            #print ("workers= ",num_workers,"  alive=",alive)
            time.sleep(1)

    except KeyboardInterrupt:
        grade_items_logging.log_message(message="grade_scheduler.py keyboard interrupt")

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

    grade_items_logging.log_message(message="grade_scheduler.py terminated")


# ==================================================================================
if __name__ == "__main__":
    num_workers = NUM_GRADING_SCHEDULER_WORKERS_int
    launch_workers(num_workers)
