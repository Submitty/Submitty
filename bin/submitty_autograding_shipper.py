#!/usr/bin/env python3

import os
import sys
import time
import signal
import json
import grade_items_logging
import grade_item
import datetime
from submitty_utils import glob
import multiprocessing
from submitty_utils import dateutils, glob

# ==================================================================================
# these variables will be replaced by INSTALL_SUBMITTY.sh
NUM_GRADING_SCHEDULER_WORKERS_string = "__INSTALL__FILLIN__NUM_GRADING_SCHEDULER_WORKERS__"
NUM_GRADING_SCHEDULER_WORKERS_int    = int(NUM_GRADING_SCHEDULER_WORKERS_string)

AUTOGRADING_LOG_PATH="__INSTALL__FILLIN__AUTOGRADING_LOG_PATH__"
SUBMITTY_DATA_DIR = "__INSTALL__FILLIN__SUBMITTY_DATA_DIR__"
HWCRON_UID = "__INSTALL__FILLIN__HWCRON_UID__"
INTERACTIVE_QUEUE = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_queue")

# ==================================================================================
def initialize(untrusted_queue):
    """
    Initializer function for all our processes. We get one untrusted user off our queue which
    we then set in our Process. We cannot recycle the shipper process as else the untrusted user
    we set for this process will be lost.

    :param untrusted_queue: multiprocessing.queues.Queue that contains all untrusted users left to
                            assign
    """
    multiprocessing.current_process().untrusted = untrusted_queue.get()

# ==================================================================================
def grade_queue_file(queue_file,which_machine,which_untrusted):
    """
    Oversees the autograding of single item from the queue

    :param queue_file: details of what to grade
    :param which_machine: name of machine to send this job to (might be "localhost")
    :param which_untrusted: specific untrusted user for this autograding job
    """

    my_dir,my_file=os.path.split(queue_file)
    pid = os.getpid()
    directory = os.path.dirname(os.path.realpath(queue_file))
    name = os.path.basename(os.path.realpath(queue_file))
    grading_file = os.path.join(directory, "GRADING_" + name)

    try:
        # prepare the job
        grade_item.just_grade_item_A(my_dir, queue_file, which_untrusted, which_machine)

        # then wait for grading to be completed
        shipper_counter=0
        while not grade_item.just_grade_item_C(my_dir, queue_file, which_untrusted, which_machine):
            shipper_counter+=1
            time.sleep(1)
            if shipper_counter >= 10:
                print (which_machine,which_untrusted,"shipper waiting: ",queue_file)
                shipper_counter=0

    except Exception as e:
        print ("ERROR attempting to grade item: ", queue_file, " exception=",e)
        grade_items_logging.log_message(message="ERROR attempting to grade item: " + queue_file + " exception " + repr(e))

    # note: not necessary to acquire lock for these statements, but
    # make sure you remove the queue file, then the grading file
    try:
        os.remove(queue_file)
    except:
        print ("ERROR attempting to remove queue file: ", queue_file)
        grade_items_logging.log_message(message="ERROR attempting to remove queue file: " + queue_file)
    try:
        os.remove(grading_file)
    except:
        print ("ERROR attempting to remove grading file: ", grading_file)
        grade_items_logging.log_message(message="ERROR attempting to remove grading file: " + grading_file)


# ==================================================================================
def get_job(which_machine,which_untrusted,overall_lock):
    """
    Picks a job from the queue

    :param overall_lock: a lock on the directory containing all queue files
    """

    time_get_job_begin = dateutils.get_current_time()

    overall_lock.acquire()
    folder= INTERACTIVE_QUEUE

    # Grab all the files currently in the folder, sorted by creation
    # time, and put them in the queue to be graded
    files = glob.glob(os.path.join(folder, "*"))
    files.sort(key=os.path.getctime)

    my_job=""

    for full_path_file in files:
        # get the file name (without the path)
        just_file = full_path_file[len(folder)+1:]
        # skip items that are already being graded
        if (just_file[0:8]=="GRADING_"):
            continue
        if os.path.exists(os.path.join(folder,"GRADING_"+just_file)):
            continue

        # found something to do
        with open(full_path_file, 'r') as infile:
            queue_obj = json.load(infile)

        # prioritize interactive jobs over (batch) regrades
        # if you've found an interactive job, exit early (since they are sorted by timestamp)
        if not "regrade" in queue_obj or not queue_obj["regrade"]:
            my_job = just_file
            break

        # otherwise it's a regrade, and if we don't already have a
        # job, take it, but we have to search the rest of the list
        if my_job == "":
            my_job = just_file

    if not my_job == "":
        grading_file = os.path.join(folder, "GRADING_" + my_job)
        # create the grading file
        open(os.path.join(grading_file), "w").close()

    overall_lock.release()

    time_get_job_end = dateutils.get_current_time()

    time_delta = time_get_job_end-time_get_job_begin
    if time_delta > datetime.timedelta(milliseconds=100):
        print ("WARNING: submitty_autograding shipper get_job time ", time_delta)
        grade_items_logging.log_message(message="WARNING: submitty_autograding shipper get_job time "+str(time_delta))

    return my_job


# ==================================================================================
# ==================================================================================
def shipper_process(overall_lock,which_machine,which_untrusted):
    """
    Each shipper process spins in a loop, looking for a job that
    matches the capabilities of this machine, and then oversees the
    autograding of that job.  Interactive jobs are prioritized over
    batch (regrade) jobs.  If no jobs are available, the shipper waits
    on an event editing one of the queues.
    """

    # ignore keyboard interrupts in the shipper processes
    signal.signal(signal.SIGINT, signal.SIG_IGN)

    counter=0

    try:
        while True:
            my_job = get_job(which_machine,which_untrusted,overall_lock)
            if not my_job == "":
                counter=0
                grade_queue_file(os.path.join(INTERACTIVE_QUEUE,my_job),which_machine,which_untrusted)
                continue
            else:
                if counter == 0 or counter >= 10:
                    print (which_machine,which_untrusted,"no available job")
                    counter=0
                counter+=1
                time.sleep(1)

    except Exception as e:
        print ("ERROR exiting shipper exception=",e)


# ==================================================================================
# ==================================================================================
def launch_shippers(num_workers):

    # verify the hwcron user is running this script
    if not int(os.getuid()) == int(HWCRON_UID):
        raise SystemExit("ERROR: the grade_item.py script must be run by the hwcron user")

    grade_items_logging.log_message(message="grade_scheduler.py launched")

    for file_path in glob.glob(os.path.join(INTERACTIVE_QUEUE, "GRADING_*")):
        grade_items_logging.log_message(message="Remove old queue file: " + file_path)
        os.remove(file_path)

    for file_path in glob.glob(os.path.join(SUBMITTY_DATA_DIR,"autograding_TODO","*")):
        grade_items_logging.log_message(message="Remove autograding TODO file: " + file_path)
        os.remove(file_path)
    for file_path in glob.glob(os.path.join(SUBMITTY_DATA_DIR,"autograding_DONE","*")):
        grade_items_logging.log_message(message="Remove autograding DONE file: " + file_path)
        os.remove(file_path)

    # prepare a list of untrusted users to be used by the shippers
    untrusted_users = multiprocessing.Queue()
    for i in range(num_workers):
        untrusted_users.put("untrusted" + str(i).zfill(2))

    # this lock will be used to edit the queue or new job event
    overall_lock = multiprocessing.Lock()

    which_machine="localhost"

    # launch the shipper threads
    processes = list()
    for i in range(0,num_workers):
        u = "untrusted" + str(i).zfill(2)
        p = multiprocessing.Process(target=shipper_process,args=(overall_lock,which_machine,u))
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
                grade_items_logging.log_message(message="ERROR: #shippers="+str(num_workers)+" != #alive="+str(alive))
            #print ("shippers= ",num_workers,"  alive=",alive)
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
    launch_shippers(num_workers)
