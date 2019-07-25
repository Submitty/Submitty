#!/usr/bin/env python3

import time
import argparse
import json
import os

SUBMITTY_DATA_DIR = "/var/local/submitty"

"""
# USAGE
# make_generated_output.py   <path to forms directory>   <semester>  <course> 
"""

def parse_args():
    parser = argparse.ArgumentParser()
    parser.add_argument("forms_path")
    parser.add_argument("semester")
    parser.add_argument("course")
    parse.
    return parser.parse_args()

def main():
    args = parse_args()
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
            config_file_path = obj["config_path"]
            with open(config_file_path,'r') as infile:
                config_file = json.load(infile)
            required_capabilities = config_file.get('required_capabilities','default')
            testcases = config_file.get('testcases',[])
            graded_file = {
                "semester": args.semester,
                "course": args.course,
                "gradeable": g_id,
                "required_capabilities": required_capabilities,
                "queue_time": time.localtime(),
                "generate_output": True,
            }
            should_generated_output = False
            for testcase in testcases:
                input_generation_commands = testcase.get('input_generation_commands',[])
                if input_generation_commands
                    should_generated_output = True
                    break

            if should_generated_output:
                path_grading_file = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_queue", "__".join([args.semester, args.course, g_id]))
                with open(path_grading_file, 'a') as grading_file:
                    json.dump(graded_file, grading_file)
