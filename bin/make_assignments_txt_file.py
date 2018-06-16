#!/usr/bin/env python3

"""
# USAGE
# make_assignments_txt_file.py   <path to forms directory>   <path to ASSIGNMENTS.txt file>   <OPTIONAL GRADEABLES>
"""

import argparse
import json
import os
import stat
import sys


def parse_args():
    parser = argparse.ArgumentParser()
    parser.add_argument("forms_path")
    parser.add_argument("assignment_path")
    parser.add_argument("gradeables", nargs="*", metavar="GRADEABLE")
    parser.add_argument("--clean",dest="clean",action='store_const',const=True,default=False,
                        help="Delete previous files and make from scratch.")
    parser.add_argument("--no_build",dest="no_build",action='store_const',const=True,default=False,
                        help="Do not (re-)build assignment.")
    return parser.parse_args()


def main():
    args = parse_args()

    # grab the gradeables from the command line (if any)
    processed = []

    # extract the semester & course from the forms_path directory
    dirs = args.forms_path.split("/")
    semester = dirs[len(dirs)-4]
    course = dirs[len(dirs)-3]

    with open(args.assignment_path, 'w') as outfile:

        #####################################
        # CLEANUP GRADEABLES THAT HAVE BEEN DELETED
        # loop over all of the previously build gradeables
        for build_dir in sorted(os.listdir(os.path.join(args.forms_path,"..","..","build"))):
            # check to see that the corresponding form json still exists
            test_form_path = os.path.join(args.forms_path,"form_"+build_dir+".json")
            if not os.path.isfile(test_form_path):
                # deletion of the form .json indicates that we should cleanup the build for that gradeable
                print ("Gradeable form file "+test_form_path+" no longer exists.  Cleanup deleted gradeable!")
                outfile.write("clean_homework  "                 +semester+"  "+course+"  "+build_dir+"\n")

        #####################################
        # OPEN ALL FILES IN THE FORMS DIRECTORIES
        sorted_list = sorted(os.listdir(args.forms_path))
        for filename in sorted_list:
            length = len(filename)
            extension = filename[length-5:length]
            if extension != ".json":
                continue
            json_filename = os.path.join(args.forms_path, filename)
            if os.path.isfile(json_filename):
                with open(json_filename, 'r') as infile:
                    obj = json.load(infile)
            else:
                sys.exit(1)

            if obj is None:
                print("whoops, this file did not load as a json object: ",filename)
                continue

            # ONLY ELECTRONIC GRADEABLES HAVE A CONFIG PATH
            if "config_path" in obj:
                g_id = obj["gradeable_id"]

                if len(args.gradeables) == 0 or g_id in args.gradeables:
                    config_path = obj["config_path"]
                    if args.clean:
                        outfile.write("clean_homework  "                 +semester+"  "+course+"  "+g_id+"\n")
                    if not args.no_build:
                        outfile.write("build_homework  "+config_path+"  "+semester+"  "+course+"  "+g_id+"\n")
                    processed.append(g_id)
                else:
                    # print("SKIPPING " + g_id)
                    continue

    # confirm all specified gradeables were processed
    for g in args.gradeables:
        if g not in processed:
            print("WARNING: " + g + " is not a valid electronic gradeable")

    #####################################
    # SET PERMISSION ON ASSIGNMENTS.txt file
    try:
        os.chmod(sys.argv[2], stat.S_IRUSR | stat.S_IRGRP | stat.S_IWUSR | stat.S_IWGRP)
    except OSError:
        pass

if __name__ == "__main__":
    main()
