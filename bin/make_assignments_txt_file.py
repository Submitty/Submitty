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
    return parser.parse_args()


def main():
    args = parse_args()

    # grab the gradeables from the command line (if any)
    processed = []


    #####################################
    # OPEN ALL FILES IN THE FORMS DIRECTORIES
    with open(args.assignment_path, 'w') as outfile:
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

            # ONLY ELECTRONIC GRADEABLES HAVE A CONFIG PATH
            if "config_path" in obj:
                g_id = obj["gradeable_id"]

                if len(args.gradeables) == 0 or g_id in args.gradeables:
                    config_path = obj["config_path"]
                    dirs = args.forms_path.split("/")
                    semester = dirs[len(dirs)-4]
                    course = dirs[len(dirs)-3]
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
