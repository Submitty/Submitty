#!/usr/bin/env python3
"""
Takes in a submission directory full of textbox answers that should
have left vs right handedness for students, and then writes a file for
use with Rainbow Grades exam seating.
"""

import argparse
import os
import json


def parse_args():
    """
    Parse the arguments for this script and return the namespace from argparse.
    """
    parser = argparse.ArgumentParser(description="Reads in left right handedness from the submission "
                                                 "directory, and then writes it to the given "
                                                 "remote file.")
    parser.add_argument("submission_directory", type=str, help="Directory of submissions that "
                        "contain a 'textbox_0.txt' file that references left right handedness")
    parser.add_argument("remote_id_file", type=str)
    return parser.parse_args()


def main():
    """
    Main execution function
    """
    args = parse_args()

    if not os.path.isdir(args.submission_directory):
        raise SystemExit("Specified submission_directory does not exist or is not a directory")
    if not os.path.isfile(args.remote_id_file):
        raise SystemExit("Specified remote_id_file does not exist or is not a file")
    indir = args.submission_directory
    outfile = args.remote_id_file

    ############################
    # OPEN THE OUTPUT FILE
    with open(outfile, 'w') as remote_ids:
        # LOOP OVER ALL OF THE USERS
        for username in os.listdir(indir):
            userdir = indir + '/' + username
            uas = userdir + '/user_assignment_settings.json'
            with open(uas) as json_data:
                d = json.load(json_data)
                active = d['active_version']

                # SKIP CANCELLED SUBMISSION
                if active < 1:
                    continue

                # GRAB THE FILE FROM THE SUBMISSION
                myfile = userdir + '/'+str(active) + '/left_right.txt'
                with open(myfile) as f:
                    handedness = f.read()

                    handedness = handedness.lower()
                    l = "left" in handedness
                    r = "right" in handedness

                    if l and not r:
                        handedness="left"
                    elif r and not l:
                        handedness="right"
                    else:
                        handedness="unknown"
                        print ("UNKNOWN",handedness,username)
                    
                    # WRITE TO EXPECTED FORMAT
                    remote_ids.write('{0} {1}\n'.format(username,handedness))
                    

if __name__ == "__main__":
    main()
