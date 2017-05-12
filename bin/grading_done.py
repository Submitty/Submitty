#!/usr/bin/env python3

import argparse
import os
import subprocess
import time

SUBMITTY_INSTALL_DIR = "__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__"
SUBMITTY_DATA_DIR = "__INSTALL__FILLIN__SUBMITTY_DATA_DIR__"
INTERACTIVE_QUEUE = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_interactive")
BATCH_QUEUE = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_batch")

if not os.path.isdir(INTERACTIVE_QUEUE):
    raise SystemError("ERROR: interactive queue {} does not exist".format(INTERACTIVE_QUEUE))
if not os.path.isdir(BATCH_QUEUE):
    raise SystemError("ERROR: batch queue {} does not exist".format(BATCH_QUEUE))
if not os.access(INTERACTIVE_QUEUE, os.R_OK):
    print("WARNING: interactive queue {} is not readable".format(INTERACTIVE_QUEUE))
if not os.access(BATCH_QUEUE, os.R_OK):
    raise SystemError("ERROR: batch queue {} is not readeable".format(BATCH_QUEUE))


def parse_args():
    parser = argparse.ArgumentParser()
    parser.add_argument("--continuous", action="store_true", default=False)
    return parser.parse_args()


def main():
    args = parse_args()
    while True:
        proc = subprocess.Popen(["pgrep", "grade_students"], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        out, _ = proc.communicate()
        grading_processes = len(str(out).strip().split("\n"))

        interactive_queue = os.listdir(INTERACTIVE_QUEUE)
        grading_interactive_queue = list(filter(lambda x: x.startswith("GRADING"), interactive_queue))
        batch_queue = os.listdir(BATCH_QUEUE)
        grading_batch_queue = list(filter(lambda x: x.startswith("GRADING"), batch_queue))

        print("GRADING PROCESSES:{:3d}       ".format(grading_processes), end="")

        if not os.access(INTERACTIVE_QUEUE, os.R_OK):
            print("INTERACTIVE todo:{:3d} ".format(len(interactive_queue)-len(grading_interactive_queue)), end="")
            if len(grading_interactive_queue) == 0:
                print("                 ", end="")
            else:
                print("(grading:{:3d})    ".format(len(grading_interactive_queue)), end="")

        print("BATCH todo:%3d ".format(len(batch_queue) - len(grading_batch_queue)))
        if len(grading_batch_queue) != 0:
            print("(grading:{:3d})".format(len(grading_batch_queue)), end="")
        print()

        if len(interactive_queue) + len(batch_queue) == 0 and not args.continuous:
            raise SystemExit()

        time.sleep(5)


if __name__ == "__main__":
    main()
