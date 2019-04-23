#!/usr/bin/env python3

"""
This script is used to monitor the contents of the two grading
queues (interactive & batch).

USAGE:
./grading_done.py
    [or]
./grading_done.py --continuous
"""

import argparse
import os
from pathlib import Path
import subprocess
import time
import psutil
import json
import time

CONFIG_PATH = os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', 'config')
with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
    OPEN_JSON = json.load(open_file)
SUBMITTY_INSTALL_DIR = OPEN_JSON['submitty_install_dir']
SUBMITTY_DATA_DIR = OPEN_JSON['submitty_data_dir']
GRADING_QUEUE = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_queue")

with open(os.path.join(CONFIG_PATH, 'submitty_users.json')) as open_file:
    OPEN_USERS_JSON = json.load(open_file)

with open(os.path.join(CONFIG_PATH, 'autograding_workers.json')) as open_file:
    OPEN_AUTOGRADING_WORKERS_JSON = json.load(open_file)

DAEMON_USER=OPEN_USERS_JSON['daemon_user']

# ======================================================================
# some error checking on the queues (& permissions of this user)

if not os.path.isdir(GRADING_QUEUE):
    raise SystemExit("ERROR: interactive queue {} does not exist".format(GRADING_QUEUE))
if not os.access(GRADING_QUEUE, os.R_OK):
    # most instructors do not have read access to the interactive queue
    print("WARNING: interactive queue {} is not readable".format(GRADING_QUEUE))

# ======================================================================

def parse_args():
    parser = argparse.ArgumentParser()
    parser.add_argument("--continuous", action="store_true", default=False)
    return parser.parse_args()


def print_helper(label,label_width,value,value_width):
    if value == 0:
        print(("{:"+str(label_width+value_width+1)+"s}").format(""), end="")
    else:
        print(("{:"+str(label_width)+"s}:{:<"+str(value_width)+"d}").format(label,value), end="")


def main():
    args = parse_args()
    while True:

        epoch_time = int(time.time())
        machine_grading_counts = {}
        for machine in OPEN_AUTOGRADING_WORKERS_JSON:
            machine_grading_counts[machine] = 0
        machine_grading_counts["NO MACHINE MATCH"] = 0
        machine_queue_counts = {}
        for machine in OPEN_AUTOGRADING_WORKERS_JSON:
            machine_queue_counts[machine] = 0
        machine_queue_counts["NO MACHINE MATCH"] = 0
        machine_stale_job = {}
        for machine in OPEN_AUTOGRADING_WORKERS_JSON:
            machine_stale_job[machine] = False

        # count the processes
        pid_list = psutil.pids()
        num_shippers=0
        num_workers=0
        for pid in pid_list:
            try:
                proc = psutil.Process(pid)
                if DAEMON_USER == proc.username():
                    if (len(proc.cmdline()) >= 2 and
                        proc.cmdline()[1] == os.path.join(SUBMITTY_INSTALL_DIR,"sbin","submitty_autograding_shipper.py")):
                        num_shippers+=1
                    if (len(proc.cmdline()) >= 2 and
                        proc.cmdline()[1] == os.path.join(SUBMITTY_INSTALL_DIR,"sbin","submitty_autograding_worker.py")):
                        num_workers+=1
            except psutil.NoSuchProcess:
                pass

        # remove 1 from the count...  each worker is forked from the
        # initial process
        num_shippers-=1
        num_workers-=1

        if num_shippers <= 0:
            print ("WARNING: No matching submitty_autograding_shipper.py processes!")
            num_shippers = 0
        if num_workers <= 0:
            print ("WARNING: No matching (local machine) submitty_autograding_worker.py processes!")
            num_workers = 0

        done = True

        # most instructors do not have read access to the interactive queue
        interactive_count = 0
        interactive_grading_count = 0
        regrade_count = 0
        regrade_grading_count = 0

        for full_path_file in Path(GRADING_QUEUE).glob("*"):
            full_path_file = str(full_path_file)
            json_file = full_path_file

            # get the file name (without the path)
            just_file = full_path_file[len(GRADING_QUEUE)+1:]
            # skip items that are already being graded
            is_grading = just_file[0:8]=="GRADING_"
            is_regrade = False

            if is_grading:
                json_file = os.path.join(GRADING_QUEUE,just_file[8:])
            try:
                start_time = os.path.getmtime(json_file)
                elapsed_time = epoch_time-start_time
                with open(json_file, 'r') as infile:
                    queue_obj = json.load(infile)
                if "regrade" in queue_obj:
                    is_regrade = queue_obj["regrade"]
            except:
                print ("whoops",json_file,end="")
                elapsed_time = 0

            if is_grading:
                if is_regrade:
                    regrade_grading_count+=1
                else:
                    interactive_grading_count+=1
            else:
                if is_regrade:
                    regrade_count+=1
                else:
                    interactive_count+=1

            capability = queue_obj["required_capabilities"]
            max_time = queue_obj["max_possible_grading_time"]

            match = False

            for machine in OPEN_AUTOGRADING_WORKERS_JSON:
                if capability in OPEN_AUTOGRADING_WORKERS_JSON[machine]["capabilities"]:
                    if is_grading:
                        machine_grading_counts[machine]+=1
                        if (elapsed_time > max_time):
                            machine_stale_job[machine] = True
                            stale = True
                            print ("--> STALE JOB: {:5d} seconds   {:s}".format(int(elapsed_time),json_file))
                    else:
                        machine_queue_counts[machine]+=1
                    match = True
            if match==False:
                machine_grading_counts["NO MACHINE MATCH"]+=1

        print("S:{:<3d} ".format(num_shippers), end="")
        print("W:{:<3d}   ".format(num_workers), end="")

        print("INTERACTIVE ", end="")
        print_helper("grading",7,interactive_grading_count,3)
        interactive_queue_count = interactive_count-interactive_grading_count
        print_helper("queue",5,interactive_queue_count,3)
        if interactive_count != 0:
            done = False

        print("REGRADE ", end="")
        print_helper("grading",7,regrade_grading_count,3)
        regrade_queue_count = regrade_count-regrade_grading_count
        print_helper("queue",5,regrade_queue_count,3)

        if regrade_count != 0:
            done = False

        for machine in OPEN_AUTOGRADING_WORKERS_JSON:
            num = OPEN_AUTOGRADING_WORKERS_JSON[machine]["num_autograding_workers"]
            if machine_stale_job[machine]:
                print (" **",end="")
            else:
                print ("   ",end="")
            print ("{:s}{:4s} ".format(machine.upper(),"["+str(num)+"]"),end="")
            print_helper("g",1,machine_grading_counts[machine],3)
            print_helper("q",1,machine_queue_counts[machine]-machine_grading_counts[machine],3)

        print()

        # quit when the queues are empty
        if done and not args.continuous:
            raise SystemExit()

        # pause before checking again
        time.sleep(5)


if __name__ == "__main__":
    main()
