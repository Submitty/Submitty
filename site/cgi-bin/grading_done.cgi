#!/usr/bin/env python3

import cgi
import json
import os
import time
from pathlib import Path
from collections import OrderedDict

# Get rid of extra .. when done testing
CONFIG_PATH = os.path.join(os.path.dirname(os.path.realpath(__file__)), '../../', 'config')
with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
    OPEN_JSON = json.load(open_file)
SUBMITTY_INSTALL_DIR = OPEN_JSON['submitty_install_dir']
SUBMITTY_DATA_DIR = OPEN_JSON['submitty_data_dir']
GRADING_QUEUE = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_queue")
IN_PROGRESS_DIR = os.path.join(SUBMITTY_DATA_DIR, "in_progress_grading")

with open(os.path.join(CONFIG_PATH, 'autograding_workers.json')) as open_file:
    OPEN_AUTOGRADING_WORKERS_JSON = json.load(open_file) 
GLOBAL_LAST_PRINT = 100

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

    stale = False
    error = ""

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
            error = f"ERROR: {job_file} requires {capability} which is not provided by any worker"
        else:
            capability_queue_counts[capability] += 1

            enabled_worker = False
            for machine in OPEN_AUTOGRADING_WORKERS_JSON:
                if OPEN_AUTOGRADING_WORKERS_JSON[machine]["enabled"]:
                    if capability in OPEN_AUTOGRADING_WORKERS_JSON[machine]["capabilities"]:
                        enabled_worker = True

            if not enabled_worker:
                error = f"ERROR: {job_file} requires {capability} which is not provided by any *ENABLED* worker"

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
            stale = True
    return {job_file:
        {
            "elapsed_time": entry.elapsed_time,
            "stale": stale,
            "error": error
        }
    }

def getGradingMachineJson():
    epoch_time = int(time.time())

    machine_grading_counts = {}
    for machine in OPEN_AUTOGRADING_WORKERS_JSON:
        machine_grading_counts[machine] = 0

    capability_queue_counts = {}
    for machine in OPEN_AUTOGRADING_WORKERS_JSON:
        m = OPEN_AUTOGRADING_WORKERS_JSON[machine]
        for c in m["capabilities"]:
            capability_queue_counts[c] = 0

    sorted_capability_queue_counts = OrderedDict(sorted(capability_queue_counts.items()))

    machine_stale_job = {}
    for machine in OPEN_AUTOGRADING_WORKERS_JSON:
        machine_stale_job[machine] = False

    # most instructors do not have read access to the interactive queue
    queue_counts = {}
    queue_counts["interactive"] = 0
    queue_counts["interactive_grading"] = 0
    queue_counts["regrade"] = 0
    queue_counts["regrade_grading"] = 0

    job_files = {}

    # collect all the jobs that are still in the queue
    for full_path_file in Path(GRADING_QUEUE).glob("*"):
        job_file = update_queue_counts(full_path_file, False, epoch_time, queue_counts,
                            sorted_capability_queue_counts, machine_grading_counts,
                            machine_stale_job)
        job_files.update(job_file)

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
            job_file = update_queue_counts(grading_file, True, epoch_time, queue_counts,
                                sorted_capability_queue_counts, machine_grading_counts,
                                machine_stale_job)
            job_files.update(job_file)

        for not_yet_started in all_files:
            job_file = update_queue_counts(not_yet_started, False, epoch_time, queue_counts,
                                sorted_capability_queue_counts, machine_grading_counts,
                                machine_stale_job)
            job_files.update(job_file)

    # format the json to be returned here
    j = {
        "machine_grading_counts": machine_grading_counts,
        "capability_queue_counts": sorted_capability_queue_counts,
        "machine_stale_job": machine_stale_job,
        "queue_counts": queue_counts,
        "job_files": job_files
    }
    return json.dumps(j, sort_keys=False, indent=4)


if __name__ == "__main__":
    print("Content-type: application/json")
    print()
    print(getGradingMachineJson())
