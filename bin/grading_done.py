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
import subprocess
import time

# these variables will be replaced by INSTALL_SUBMITTY.sh
SUBMITTY_INSTALL_DIR = "__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__"
SUBMITTY_DATA_DIR = "__INSTALL__FILLIN__SUBMITTY_DATA_DIR__"

INTERACTIVE_QUEUE = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_interactive")
BATCH_QUEUE = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_batch")


# ======================================================================
# some error checking on the queues (& permissions of this user)

if not os.path.isdir(INTERACTIVE_QUEUE):
    raise SystemExit("ERROR: interactive queue {} does not exist".format(INTERACTIVE_QUEUE))
if not os.path.isdir(BATCH_QUEUE):
    raise SystemExit("ERROR: batch queue {} does not exist".format(BATCH_QUEUE))
if not os.access(INTERACTIVE_QUEUE, os.R_OK):
    # most instructors do not have read access to the interactive queue
    print("WARNING: interactive queue {} is not readable".format(INTERACTIVE_QUEUE))
if not os.access(BATCH_QUEUE, os.R_OK):
    raise SystemExit("ERROR: batch queue {} is not readeable".format(BATCH_QUEUE))


# ======================================================================

def parse_args():
    parser = argparse.ArgumentParser()
    parser.add_argument("--continuous", action="store_true", default=False)
    return parser.parse_args()


def main():
    args = parse_args()
    while True:

        #
        # FIXME: This command is buggy, sometimes overcounting grading processes
        #
        # we are attempting to count instances of
        #    /usr/local/submitty/bin/grade_students.sh
        # but it may also be matching:
        #    /usr/local/submitty/bin/write_grade_history.py
        #
        # and it perhaps also overcounts submissions that use fork (??)
        #

        proc = subprocess.Popen(["pgrep", "grade_students"], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        out, _ = proc.communicate()
        out = out.decode("utf-8")
        procs = out.split('\n')
        # extra newline creates an empty process (also needed when no matching grade processes)
        procs = [s for s in procs if len(s) > 0]

        # count the processes
        num_procs = len(procs)
        if num_procs == 0:
            print ("WARNING: No matching grade_students.sh processes!")

        done = True
        batch_queue = os.listdir(BATCH_QUEUE)
        grading_batch_queue = list(filter(lambda x: x.startswith("GRADING"), batch_queue))
        if len(batch_queue) != 0:
            done = False

        print("GRADING PROCESSES:{:3d}       ".format(num_procs), end="")

        if os.access(INTERACTIVE_QUEUE, os.R_OK):
            # most instructors do not have read access to the interactive queue
            interactive_queue = os.listdir(INTERACTIVE_QUEUE)
            grading_interactive_queue = list(filter(lambda x: x.startswith("GRADING"), interactive_queue))
            print("INTERACTIVE todo:{:3d} ".format(len(interactive_queue)-len(grading_interactive_queue)), end="")
            if len(grading_interactive_queue) == 0:
                print("                 ", end="")
            else:
                print("(grading:{:3d})    ".format(len(grading_interactive_queue)), end="")
            if len(interactive_queue) != 0:
                done = False

        print("BATCH todo:{:3d} ".format(len(batch_queue) - len(grading_batch_queue)), end="")
        if len(grading_batch_queue) != 0:
            print("(grading:{:3d})".format(len(grading_batch_queue)), end="")
        print()

        # quit when the queues are empty
        if done and not args.continuous:
            raise SystemExit()

        # pause before checking again
        time.sleep(5)


if __name__ == "__main__":
    main()
