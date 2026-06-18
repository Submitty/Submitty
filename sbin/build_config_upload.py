#!/usr/bin/env python3
#
# This script is run by a cron job as the DAEMON_USER
#
# Regularly checks a queue to rebuild assignment configurations for recently modified gradeables.
#

import os
from pathlib import Path
import pwd
import time
import subprocess
import json

CONFIG_PATH = os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', 'config')
with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
    JSON = json.load(open_file)
DATA_DIR = JSON['submitty_data_dir']

# ------------------------------------------------------------------------
def build_one(data):
    semester = data["term"]
    course = data["course"]

    # construct the paths for this course
    build_script = os.path.join(DATA_DIR, "courses", semester, course, "BUILD_" + course + ".sh")
    build_output = os.path.join(DATA_DIR, "courses", semester, course, "build_script_output.txt")

    # construct the command line to build/rebuild/clean/delete the gradeable
    build_args = [build_script]
    if "gradeable" in data:
        build_args.append(data["gradeable"])
    if "clean" in data:
        build_args.append("--clean")
    if "no_build" in data:
        build_args.append("--no_build")

    with open(build_output, "w") as open_file:
        subprocess.call(build_args, stdout=open_file, stderr=open_file)


# ------------------------------------------------------------------------
def build_all():
    for filename in Path(DATA_DIR, "to_be_built").glob("*.json"):
        with open(filename) as data_file:
            print("going to process: " + filename)
            data = json.load(data_file)
            # after loading the contents of the file, remove it first
            os.remove(filename)
            # then build it, because build is slow (and we might have a race condition)
            build_one(data)
            print("finished with " + filename)


# ------------------------------------------------------------------------
# MAIN LOOP

# this script should only run for 5 minutes, then another process running
# this script will take over

# ------------------------------------------------------------------------
# this script is intended to be run only from the cron job of DAEMON_USER
def main():
    username = pwd.getpwuid(os.getuid()).pw_name
    if username != "submitty_daemon":
        raise SystemError("ERROR!  This script must be run by submitty_daemon")

    # ensure future pushd & popd commands don't complain
    os.chdir(os.path.join(DATA_DIR, "to_be_built"))

    start = time.time()
    count = 0
    while True:
        count += 1
        now = time.time()
        formattedtime = time.strftime('%X %x %Z')
        print("{:s} build_config_upload.py loop {:d}".format(formattedtime, count))

        build_all()

        # stop if its been more than 5 minutes
        if (now-start) >= 5 * 60:
            print ("exiting for time")
            raise SystemExit()

        # sleep for 5 seconds
        time.sleep(5)

# ------------------------------------------------------------------------

if __name__ == "__main__":
    main()
