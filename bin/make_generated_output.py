#!/usr/bin/env python3

import time
import argparse
import json
import os
import re
from submitty_utils import dateutils

SUBMITTY_DATA_DIR = "/var/local/submitty"

"""
# USAGE
# make_generated_output.py   <path to config file for gradeable>   <assignment>   <semester>  <course> 
"""

def parse_args():
    parser = argparse.ArgumentParser()
    parser.add_argument("config_file_path")
    parser.add_argument("assignment")
    parser.add_argument("semester")
    parser.add_argument("course")
    return parser.parse_args()

def main():
    args = parse_args()
    complete_config_json_path = os.path.join(SUBMITTY_DATA_DIR,'courses',args.semester,args.course,'config','complete_config','complete_config_' + args.assignment + '.json')
    print(complete_config_json_path)
    if os.path.isfile(complete_config_json_path):
        with open(complete_config_json_path,'r', encoding='utf-8') as infile:
            config_file=json.load(infile)
    else:
        sys.exit(1)

    required_capabilities = config_file.get('required_capabilities','default')
    testcases = config_file.get('testcases',[])
    graded_file = {
        "semester": args.semester,
        "course": args.course,
        "gradeable": args.assignment,
        "required_capabilities": required_capabilities,
        "queue_time": dateutils.write_submitty_date(microseconds=True),
        "generate_output": True,
    }
    should_generated_output = False
    for testcase in testcases:
        input_generation_commands = testcase.get('input_generation_commands',[])
        validations = testcase.get('validation',[])
        for validation in validations:
            output_generation_commands = validation.get('command',[])
            if output_generation_commands:
                if not input_generation_commands:
                    should_generated_output = True
                    break

    if should_generated_output:
        path_grading_file = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_queue", "__".join([args.semester, args.course, args.assignment]))
        with open(path_grading_file, 'a') as grading_file:
            json.dump(graded_file, grading_file,sort_keys=True,indent=4)
        print("Starting to build generated output")

if __name__ == "__main__":
    main()