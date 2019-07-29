#!/usr/bin/env python3

import time
import argparse
import json
import os
import re

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
    config_json_path = os.path.join(args.config_file_path, 'config.json')
    print(config_json_path)
    if os.path.isfile(config_json_path):
        with open(config_json_path,'r', encoding='utf-8') as infile:
            # this hack should be removed
            infile=re.sub("//.*?\n","",infile.read())
            infile=re.sub("/\\*.*?\\*/","",infile)
            infile=re.sub(",",",\n",infile)
            config_file=json.loads(infile)
    else:
        sys.exit(1)

    required_capabilities = config_file.get('required_capabilities','default')
    testcases = config_file.get('testcases',[])
    graded_file = {
        "semester": args.semester,
        "course": args.course,
        "gradeable": args.assignment,
        "required_capabilities": required_capabilities,
        "queue_time": time.localtime(),
        "generate_output": True,
    }
    should_generated_output = False
    for testcase in testcases:
        input_generation_commands = testcase.get('input_generation_commands',[])
        if input_generation_commands:
            should_generated_output = True
            break

    if should_generated_output:
        path_grading_file = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_queue", "__".join([args.semester, args.course, args.assignment]))
        with open(path_grading_file, 'a') as grading_file:
            json.dump(graded_file, grading_file)
        print("Starting to build generated output")

if __name__ == "__main__":
    main()