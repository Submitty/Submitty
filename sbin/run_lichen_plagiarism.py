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
import json


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

    subprocess.call(['/usr/local/submitty/Lichen/bin/concatenate_all.py', config_path ])
    subprocess.call(['/usr/local/submitty/Lichen/bin/tokenize_all.py', config_path ])
    subprocess.call(['/usr/local/submitty/Lichen/bin/hash_all.py', config_path ])
    subprocess.call(['/usr/local/submitty/Lichen/bin/compare_hashes.out', config_path ])

    print("finished running lichen plagiarism for " + config_path)


# ------------------------------------------------------------------------

if __name__ == "__main__":
    main()
