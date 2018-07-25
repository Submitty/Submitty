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
    semester = data["semester"]
    course = data["course"]
    gradeable = data["gradeable"]
    language = data["language"]
    window = data["sequence_length"]
    threshold = data["threshold"]
    regrex = None
    instructor_provided_code_path = None
    prior_term_gradeables = None
    ignore_submissions = None

    # construct the command line agruments
    if data["file_option"] == "matching_regrex":
        regrex = data["regrex"]

    if data["instructor_provided_code"]:
        instructor_provided_code_path = data["instructor_provided_code_path"]

    if len(data["prev_term_gradeables"]) != 0:
        prior_term_gradeables = []
        for prior_term_gradeable in data["prev_term_gradeables"]:
            prior_term_gradeables.append(prior_term_gradeable)     

    if len(data["ignore_submissions"]) != 0:
        ignore_submissions = []
        for submission in data["ignore_submissions"]:
            ignore_submissions.append(submission)     

    subprocess.call(['/usr/local/submitty/Lichen/bin/concatenate_all.py', semester, course, gradeable ])
    subprocess.call(['/usr/local/submitty/Lichen/bin/tokenize_all.py', semester, course, gradeable, '--{}'.format(language) ])
    subprocess.call(['/usr/local/submitty/Lichen/bin/hash_all.py', semester, course, gradeable, '--window', window, '--{}'.format(language) ])
    subprocess.call(['/usr/local/submitty/Lichen/bin/compare_hashes.out', semester, course, gradeable, '--window', window ])


# ------------------------------------------------------------------------
# MAIN LOOP

# ------------------------------------------------------------------------
# this script is intended to be run only from the cron job of DAEMON_USER
def main():
    username = pwd.getpwuid(os.getuid()).pw_name
    if username != "submitty_daemon":
        raise SystemError("ERROR!  This script must be run by submitty_daemon")

    if len(sys.argv) != 4:
        raise SystemError("ERROR!  This script must be given 3 argument which should be path of lichen config")

    semester = sys.argv[1];
    course = sys.argv[2];   
    gradeable = sys.argv[3];
    
    config_path = "/var/local/submitty/courses/"+ semester + "/" +course+ "/lichen/config/lichen_"+ semester+"_"+ course+ "_" +gradeable+".json" 
    with open(config_path) as saved_config:
        data = json.load(saved_config)
        
        run_lichen_plagiarism(data)
        print("finished running lichen plagiarism for " + config_path)


# ------------------------------------------------------------------------

if __name__ == "__main__":
    main()
