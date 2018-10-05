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
        raise SystemExit("ERROR: Specified submission_directory is invalid: "+args.submission_directory)

    try:

        ############################
        # OPEN THE OUTPUT FILE
        with open(args.remote_id_file, 'w') as remote_ids:
            # LOOP OVER ALL OF THE USERS
            for username in os.listdir(args.submission_directory):
                userdir = args.submission_directory + '/' + username
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
                        iclicker_string = f.read()
                        iclicker_ids = iclicker_string.split(',')
                        if len(iclicker_ids) > 1:
                            print ("NOTE: user '{0}' has entered '{1}' Remote IDs".format(username,len(iclicker_ids)))
                        for iclicker in iclicker_ids:
                            if len(iclicker) != 8:
                                print ("WARNING! iclicker id '{0}' for user '{1}' is not 8 characters".format(iclicker,username))
                            if 'T24' in iclicker or 't24' in iclicker:
                                print ("WARNING! iclicker id '{0}' for user '{1}' is likely incorrect (model # not id #)".format(iclicker,username))

                            # WRITE TO EXPECTED FORMAT (matches iclicker.com format)
                            remote_ids.write('#{0},"{1}"\n'.format(iclicker.upper(),username))

    except IOError:
        raise SystemExit("ERROR: Cannot write to specified remote_id_file: "+args.remote_id_file);




if __name__ == "__main__":
    main()
