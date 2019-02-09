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

CONFIG_PATH = os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', 'config')
with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
    OPEN_JSON = json.load(open_file)
SUBMITTY_INSTALL_DIR = OPEN_JSON['submitty_install_dir']
SUBMITTY_DATA_DIR = OPEN_JSON['submitty_data_dir']
GRADING_QUEUE = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_queue")

with open(os.path.join(CONFIG_PATH, 'submitty_users.json')) as open_file:
    OPEN_USERS_JSON = json.load(open_file)

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


def main():
    args = parse_args()
    while True:

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

        print("SHIPPERS:{:3d}  ".format(num_shippers), end="")
        print("WORKERS:{:3d}       ".format(num_workers), end="")

        if os.access(GRADING_QUEUE, os.R_OK):
            # most instructors do not have read access to the interactive queue
            interactive_count = 0
            interactive_grading_count = 0
            regrade_count = 0
            regrade_grading_count = 0

            for full_path_file in Path(GRADING_QUEUE).glob("*"):
                json_file = str(full_path_file)

                # get the file name (without the path)
                just_file = full_path_file[len(GRADING_QUEUE)+1:]
                # skip items that are already being graded
                is_grading = just_file[0:8]=="GRADING_"
                is_regrade = False

                if is_grading:
                    json_file = os.path.join(GRADING_QUEUE,just_file[8:])

                try:
                    with open(json_file, 'r') as infile:
                        queue_obj = json.load(infile)
                    if "regrade" in queue_obj:
                        is_regrade = queue_obj["regrade"]
                except:
                    print ("whoops",json_file,end="")

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

            print("INTERACTIVE todo:{:3d} ".format(interactive_count), end="")
            if interactive_grading_count == 0:
                print("                 ", end="")
            else:
                print("(grading:{:3d})    ".format(interactive_grading_count), end="")
            if interactive_count != 0:
                done = False

            print("BATCH todo:{:3d} ".format(regrade_count), end="")
            if regrade_grading_count == 0:
                print("                 ", end="")
            else:
                print("(grading:{:3d})    ".format(regrade_grading_count), end="")
            if regrade_count != 0:
                done = False

        print()

        # quit when the queues are empty
        if done and not args.continuous:
            raise SystemExit()

        # pause before checking again
        time.sleep(5)


if __name__ == "__main__":
    main()
