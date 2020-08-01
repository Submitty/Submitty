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
import time
import psutil
import json
import datetime

CONFIG_PATH = os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', 'config')
with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
    OPEN_JSON = json.load(open_file)
SUBMITTY_INSTALL_DIR = OPEN_JSON['submitty_install_dir']
SUBMITTY_DATA_DIR = OPEN_JSON['submitty_data_dir']
GRADING_QUEUE = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_queue")
IN_PROGRESS_DIR = os.path.join(SUBMITTY_DATA_DIR, "in_progress_grading")

with open(os.path.join(CONFIG_PATH, 'submitty_users.json')) as open_file:
    OPEN_USERS_JSON = json.load(open_file)

with open(os.path.join(CONFIG_PATH, 'autograding_workers.json')) as open_file:
    OPEN_AUTOGRADING_WORKERS_JSON = json.load(open_file)

DAEMON_USER = OPEN_USERS_JSON['daemon_user']

GLOBAL_LAST_PRINT = 100

# ======================================================================
# some error checking on the queues (& permissions of this user)

if not os.path.isdir(GRADING_QUEUE):
    raise SystemExit("ERROR: autograding queue {} does not exist".format(GRADING_QUEUE))
if not os.path.isdir(IN_PROGRESS_DIR):
    raise SystemExit(f"ERROR: grading in-progress directory {IN_PROGRESS_DIR} does not exist")
if not os.access(GRADING_QUEUE, os.R_OK):
    # most instructors do not have read access to the interactive queue
    print("WARNING: autograding queue {} is not readable".format(GRADING_QUEUE))
if not os.access(IN_PROGRESS_DIR, os.R_OK):
    print(f"WARNING: in-progress directory {IN_PROGRESS_DIR} is not readable")

# ======================================================================


def parse_args():
    parser = argparse.ArgumentParser()
    parser.add_argument("--continuous", action="store_true", default=False)
    return parser.parse_args()


def print_helper(label, label_width, value, value_width):
    if value == 0:
        print(("{:"+str(label_width+value_width+1)+"s}").format(""), end="")
    else:
        print(("{:"+str(label_width)+"s}:{:<"+str(value_width)+"d}").format(label, value), end="")


def print_header(OPEN_AUTOGRADING_WORKERS_JSON, capability_queue_counts,
                 pad_machines, pad_capabilities):

    # count the shipper & worker processes
    pid_list = psutil.pids()
    num_shippers = 0
    num_workers = 0
    for pid in pid_list:
        try:
            proc = psutil.Process(pid)
            if DAEMON_USER == proc.username():
                if (len(proc.cmdline()) >= 2 and
                    proc.cmdline()[1] ==
                    os.path.join(SUBMITTY_INSTALL_DIR,
                                 "autograder", "submitty_autograding_shipper.py")):
                    num_shippers += 1
                if (len(proc.cmdline()) >= 2 and
                    proc.cmdline()[1] ==
                    os.path.join(SUBMITTY_INSTALL_DIR,
                                 "autograder", "submitty_autograding_worker.py")):
                    num_workers += 1
        except psutil.NoSuchProcess:
            pass

    # remove 1 from the count...  each worker is forked from the
    # initial process
    num_shippers -= 1
    num_workers -= 1

    if num_shippers <= 0:
        print("WARNING: No matching submitty_autograding_shipper.py processes!")
        num_shippers = 0
    if num_workers <= 0:
        print("WARNING: No matching (local machine) submitty_autograding_worker.py processes!")
        num_workers = 0

    num_machines = len(OPEN_AUTOGRADING_WORKERS_JSON)
    num_capabilities = len(capability_queue_counts)

    machine_width = pad_machines+num_machines*9
    capabilities_width = pad_capabilities+num_capabilities*9
    table_width = machine_width + capabilities_width + 48
    print('-'*table_width)

    print("{:<3}{:9}".format(num_shippers, "SHIPPERS"), end="")
    print("{:2}".format("|"), end="")
    print("{:15}".format("INTERACTIVE"), end="")
    print("{:14}".format("REGRADE"), end="")
    print("{:2}".format("|"), end="")
    print('{x:{width}}'.format(x="MACHINES (active work)", width=machine_width), end="")
    print("{:2}".format("|"), end="")
    print('{x:{width}}'.format(x="CAPABILITIES (queue)", width=capabilities_width), end="")
    print("{:1}".format("|"), end="")
    print()

    print("{:<3}{:9}".format(num_workers, "WORKERS"), end="")
    print("{:2}".format("|"), end="")
    print("{:8}".format("grading"), end="")
    print("{:7}".format("queue"), end="")
    print("{:8}".format("grading"), end="")
    print("{:6}".format("queue"), end="")
    print("{:2}".format("|"), end="")
    for machine in OPEN_AUTOGRADING_WORKERS_JSON:
        print("{:9}".format(machine.upper()), end="")
    print(' '*pad_machines, end="")
    print("{:2}".format("|"), end="")
    for cap in capability_queue_counts:
        print("{:9}".format(cap.lower()), end="")
    print(' '*pad_capabilities, end="")
    print("{:1}".format("|"), end="")
    print()

    print("{:12}".format(""), end="")
    print("{:2}".format("|"), end="")
    print("{:29}".format(""), end="")
    print("{:2}".format("|"), end="")
    for machine in OPEN_AUTOGRADING_WORKERS_JSON:
        enabled = OPEN_AUTOGRADING_WORKERS_JSON[machine]["enabled"]
        num = OPEN_AUTOGRADING_WORKERS_JSON[machine]["num_autograding_workers"]
        if not enabled:
            num = 0
        print("{:9s}".format("["+str(num)+"]"), end="")
    print(' '*pad_machines, end="")
    print("{:2}".format("|"), end="")
    for _cap in capability_queue_counts:
        print("{:9}".format(""), end="")
    print(' '*pad_capabilities, end="")
    print("{:1}".format("|"), end="")
    print()

    print('-'*table_width)


def print_status(epoch_time,
                 queue_counts,
                 OPEN_AUTOGRADING_WORKERS_JSON,
                 machine_stale_job,
                 machine_grading_counts,
                 capability_queue_counts):

    # if there is no autograding work and the queue is empty, return true
    done = True

    num_machines = len(OPEN_AUTOGRADING_WORKERS_JSON)
    num_capabilities = len(capability_queue_counts)

    pad_machines = max(0, 23 - num_machines*9)
    pad_capabilities = max(0, 21 - num_capabilities*9)

    # print the column headings every 2 minutes
    global GLOBAL_LAST_PRINT
    if (epoch_time > GLOBAL_LAST_PRINT+120):
        GLOBAL_LAST_PRINT = epoch_time
        print_header(OPEN_AUTOGRADING_WORKERS_JSON,
                     capability_queue_counts,
                     pad_machines, pad_capabilities)

    # print time
    dt = datetime.datetime.fromtimestamp(epoch_time)
    dt_hr = dt.strftime("%H")
    dt_min = dt.strftime("%M")
    dt_sec = dt.strftime("%S")
    print(dt_hr+":"+dt_min+":"+dt_sec, end="")
    print("    | ", end="")

    # print overall interactive & regrade queue status
    print_helper("g", 1, queue_counts["interactive_grading"], 6)
    print_helper("q", 1, queue_counts["interactive"], 5)
    print_helper("g", 1, queue_counts["regrade_grading"], 6)
    print_helper("q", 1, queue_counts["regrade"], 4)
    print("{:2}".format("|"), end="")
    tmp_counts = (queue_counts["interactive"] + queue_counts["interactive_grading"] +
                  queue_counts["regrade"] + queue_counts["regrade_grading"])
    if tmp_counts != 0:
        done = False

    # print the data on currently grading work for each machine
    for machine in OPEN_AUTOGRADING_WORKERS_JSON:
        print_helper("g", 1, machine_grading_counts[machine], 4)
        if machine_stale_job[machine]:
            print("** ", end="")
        else:
            print("   ", end="")
        print("", end="")
    print(' '*pad_machines, end="")
    print("{:2}".format("|"), end="")

    # print the data on items waiting in the queue to be picked up
    for cap in capability_queue_counts:
        x = capability_queue_counts[cap]
        print_helper("q", 1, x, 4)
        print("   ", end="")
    print(' '*pad_capabilities, end="")
    print("{:1}".format("|"), end="")
    print()

    return done


class QueueItem:
    def __init__(self, json_file, epoch_time, is_grading):
        # If this is for a queue item currently grading; then we ensure the
        # provided JSON file begins with 'GRADING_', and we get rid of the
        # 'GRADING_' prefix for the regular queue file, while also reading
        # the 'GRADING_' file.
        if is_grading:
            base, tail = os.path.split(json_file)
            assert(tail.startswith('GRADING_'))

            with open(json_file, 'r') as infile:
                self.grading_queue_obj = json.load(infile)

            json_file = os.path.join(base, tail[8:])

        self.start_time = os.path.getmtime(json_file)
        self.elapsed_time = epoch_time - self.start_time
        with open(json_file, 'r') as infile:
            self.queue_obj = json.load(infile)
        self.is_regrade = "regrade" in self.queue_obj


def update_queue_counts(queue_or_grading_file, is_grading, epoch_time, queue_counts,
                        capability_queue_counts, machine_grading_counts,
                        machine_stale_job):

    job_dir, job_file = os.path.split(queue_or_grading_file)

    try:
        entry = QueueItem(queue_or_grading_file, epoch_time, is_grading)
    except Exception as e:
        print(f"Whoops: could not read for {queue_or_grading_file}: {e}")
        return

    if entry.is_regrade:
        if is_grading:
            queue_counts["regrade_grading"] += 1
        else:
            queue_counts["regrade"] += 1
    else:
        if is_grading:
            queue_counts["interactive_grading"] += 1
        else:
            queue_counts["interactive"] += 1

    capability = "default"
    if "required_capabilities" in entry.queue_obj:
        capability = entry.queue_obj["required_capabilities"]

    if not is_grading:
        if capability not in capability_queue_counts:

            print(f"ERROR: {job_file} requires {capability} which is not provided by any worker")
        else:
            capability_queue_counts[capability] += 1

            enabled_worker = False
            for machine in OPEN_AUTOGRADING_WORKERS_JSON:
                if OPEN_AUTOGRADING_WORKERS_JSON[machine]["enabled"]:
                    if capability in OPEN_AUTOGRADING_WORKERS_JSON[machine]["capabilities"]:
                        enabled_worker = True

            if not enabled_worker:
                print(f"ERROR: {job_file} requires {capability} "
                      f"which is not provided by any *ENABLED* worker")

    else:
        max_time = entry.queue_obj["max_possible_grading_time"]
        full_machine = entry.grading_queue_obj['machine']

        grading_machine = "NONE"
        for machine in OPEN_AUTOGRADING_WORKERS_JSON:
            m = OPEN_AUTOGRADING_WORKERS_JSON[machine]["address"]
            if OPEN_AUTOGRADING_WORKERS_JSON[machine]["username"] != "":
                m = f"{OPEN_AUTOGRADING_WORKERS_JSON[machine]['username']}@{m}"
            if full_machine == m:
                grading_machine = machine
                break
        machine_grading_counts[grading_machine] += 1

        if entry.elapsed_time > max_time:
            machine_stale_job[grading_machine] = True
            print(f"--> STALE JOB: {int(entry.elapsed_time):5d} seconds   {job_file:s}")


def main():
    args = parse_args()
    while True:

        epoch_time = int(time.time())

        machine_grading_counts = {}
        for machine in OPEN_AUTOGRADING_WORKERS_JSON:
            machine_grading_counts[machine] = 0

        capability_queue_counts = {}
        for machine in OPEN_AUTOGRADING_WORKERS_JSON:
            m = OPEN_AUTOGRADING_WORKERS_JSON[machine]
            for c in m["capabilities"]:
                capability_queue_counts[c] = 0

        machine_stale_job = {}
        for machine in OPEN_AUTOGRADING_WORKERS_JSON:
            machine_stale_job[machine] = False

        # most instructors do not have read access to the interactive queue
        queue_counts = {}
        queue_counts["interactive"] = 0
        queue_counts["interactive_grading"] = 0
        queue_counts["regrade"] = 0
        queue_counts["regrade_grading"] = 0

        # collect all the jobs that are still in the queue
        for full_path_file in Path(GRADING_QUEUE).glob("*"):
            update_queue_counts(full_path_file, False, epoch_time, queue_counts,
                                capability_queue_counts, machine_grading_counts,
                                machine_stale_job)

        # collect all the jobs that are in the worker directories
        for worker_folder in filter(os.path.isdir, Path(IN_PROGRESS_DIR).glob('*')):
            all_the_files = Path(worker_folder).glob('*')
            just_grading_files = Path(worker_folder).glob('GRADING_*')
            all_files = []

            for a_file in all_the_files:
                all_files.append(str(a_file))

            for grading_file in just_grading_files:
                non_grading_tmp = str((grading_file.name)[8:])
                non_grading_file = str(os.path.join(grading_file.parent, non_grading_tmp))
                try:
                    all_files.remove(non_grading_file)
                except Exception as e:
                    print(f"WARNING -- {non_grading_file} wasn't in all_files list {e}")
                try:
                    all_files.remove(str(grading_file))
                except Exception as e:
                    print(f"WARNING -- {grading_file} wasn't in all_files list {e}")
                    pass
                update_queue_counts(grading_file, True, epoch_time, queue_counts,
                                    capability_queue_counts, machine_grading_counts,
                                    machine_stale_job)

            for not_yet_started in all_files:
                update_queue_counts(not_yet_started, False, epoch_time, queue_counts,
                                    capability_queue_counts, machine_grading_counts,
                                    machine_stale_job)

        done = print_status(epoch_time,
                            queue_counts,
                            OPEN_AUTOGRADING_WORKERS_JSON,
                            machine_stale_job,
                            machine_grading_counts,
                            capability_queue_counts)

        # quit when the queues are empty
        if done and not args.continuous:
            raise SystemExit()

        # pause before checking again
        time.sleep(5)


if __name__ == "__main__":
    main()
