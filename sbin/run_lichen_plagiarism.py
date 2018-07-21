#!/usr/bin/env python3
#
# This script is run by a cron job as the DAEMON_USER
#
# Runs lichen plagiarism detector for saved configuration
#

import os
import sys
import pwd
import time
import subprocess
from submitty_utils import glob
import json


# ------------------------------------------------------------------------
def run_lichen_plagiarism(data):
    
    # construct the paths lichen script and output
    lichen_script = "/usr/local/submitty/GIT_CHECKOUT/Lichen/bin/process_all.sh"
    lichen_output = "/var/local/submitty/courses/" + data["semester"] + "/" + data["course"] + "/lichen/lichen_script_output.txt"

    # construct the command line agruments
    lichen_args = ['/bin/bash' , lichen_script , data["semester"], data["course"], data["gradeable"], '--language', data["language"], '--window', data["sequence_length"], '--threshold', data["threshold"] ]
    if data["file_option"] == "matching_regrex":
        lichen_args.append('--regrex')
        lichen_args.append(data["regrex"])

    if data["instructor_provided_code"]:
        lichen_args.append('--provided_code_path')
        lichen_args.append(data["instructor_provided_code_path"])

    if len(data["prev_term_gradeables"]) != 0:
        lichen_args.append('--prior_term_gradeables')
        for gradeable in data["prev_term_gradeables"]:
            lichen_args.append(gradeable)     

    if len(data["ignore_submissions"]) != 0:
        lichen_args.append('--ignore_submissions')
        for ignore_submission in data["ignore_submissions"]:
            lichen_args.append(ignore_submission)     

    with open(lichen_output, "w") as open_file:
        subprocess.call(lichen_args, stdout=open_file, stderr=open_file)


# ------------------------------------------------------------------------
# MAIN LOOP

# ------------------------------------------------------------------------
# this script is intended to be run only from the cron job of DAEMON_USER
def main():
    username = pwd.getpwuid(os.getuid()).pw_name
    if username != "submitty_daemon":
        raise SystemError("ERROR!  This script must be run by submitty_daemon")

    if len(sys.argv) != 2:
        raise SystemError("ERROR!  This script must be given 1 argument which should be path of lichen config")

    config_path = sys.argv[1];

    with open(config_path) as saved_config:
        data = json.load(saved_config)

        # after loading the contents of the file, remove it first
        os.remove(config_path)
        
        run_lichen_plagiarism(data)
        print("finished running lichen plagiarism for " + config_path)


# ------------------------------------------------------------------------

if __name__ == "__main__":
    main()
