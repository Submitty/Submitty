#!/usr/bin/env python3
"""
Takes in a submission directory full of textbox answers that should have iClicker IDs for 
students, and then writes that to an external file such that each row of that file is
#<iClicker_ID>,<Student_ID>
"""

import argparse
import os
import json


def parse_args():
    """
    Parse the arguments for this script and return the namespace from argparse.
    """
    parser = argparse.ArgumentParser(description="Reads in iClicker data from the submission "
                                                 "directory, and then writes it to the given "
                                                 "remote file.")
    parser.add_argument("submission_directory", type=str, help="Directory of submissions that "
                        "contain a 'textbox_0.txt' file that references iClicker ID")
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

                # GRAB THE ICLICKER FROM THE SUBMISSION
                clickerfile = userdir + '/'+str(active) + '/textbox_0.txt'
                with open(clickerfile) as f:
                    iclicker = f.read()

                    # WRITE TO EXPECTED FORMAT (matches iclicker.com format)
                    remote_ids.write('#{0},"{1}"\n'.format(iclicker.upper(),username))

if __name__ == "__main__":
    main()
