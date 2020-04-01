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
import time
from shutil import rmtree


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

    semester = sys.argv[1]
    course = sys.argv[2]
    gradeable = sys.argv[3]
    config_hash = ""
    config_regex_changed = ""
    config_path = "/var/local/submitty/courses/"+ semester + "/" +course+ "/lichen/config/lichen_"+ semester+"_"+ course+ "_" +gradeable+".json"
    log_path = f"/var/local/submitty/courses/{semester}/{course}/lichen/logs/{gradeable}/run_results.json"
    log_json = None
    rank_path = f"/var/local/submitty/courses/{semester}/{course}/lichen/ranking/{gradeable}.txt"
    matches_path = f"/var/local/submitty/courses/{semester}/{course}/lichen/matches/{gradeable}"
    hashes_path = f"/var/local/submitty/courses/{semester}/{course}/lichen/hashes/{gradeable}"
    if os.path.exists(rank_path):
        os.remove(rank_path)
    # Clear hashes/matches from previous run...
    if os.path.isdir(matches_path):
        rmtree(matches_path)
        os.mkdir(matches_path)
    if os.path.isdir(hashes_path):
        rmtree(hashes_path)
        os.mkdir(hashes_path)

    with open(config_path, 'r') as j:
        json_data = json.load(j)
        config_hash = "" if 'hash' not in json_data else json_data['hash']
        config_regex_changed = "" if 'regex_updated' not in json_data else json_data['regex_updated']
    previous_hash = None
    try:
        log_json = open(log_path, 'r+')
        previous_hash = json.load(log_json)['config_hash']
    except:
        log_json = open(log_path, 'w+')

    start_time = time.time()
    if (previous_hash == None or (previous_hash != config_hash and config_regex_changed)):
        concat_res = subprocess.call(['/usr/local/submitty/Lichen/bin/concatenate_all.py', config_path ])
        tok_res = subprocess.call(['/usr/local/submitty/Lichen/bin/tokenize_all.py', config_path ])
    hash_res = subprocess.call(['/usr/local/submitty/Lichen/bin/hash_all.py', config_path ])
    compare_res = subprocess.call(['/usr/local/submitty/Lichen/bin/compare_hashes.out', config_path ])
    end_time = time.time()
    duration = end_time - start_time
    results = {
        'config_hash': config_hash,
        'concat_result': concat_res,
        'tokenize_result': hash_res,
        'compare_result': compare_res,
        'duration': time.strftime("%H:%M:%S", time.gmtime(duration))
    }
    log_json.truncate(0)
    log_json.write(json.dumps(results))
    log_json.close()
    print("finished running lichen plagiarism for " + config_path)


# ------------------------------------------------------------------------

if __name__ == "__main__":
    main()
