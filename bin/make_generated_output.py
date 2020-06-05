#!/usr/bin/env python3

"""
# USAGE
# make_generated_output.py   <path to config file for gradeable>   <assignment>   <semester>  <course> 
"""

import argparse
import json
import os
from submitty_utils import dateutils
import sys

SUBMITTY_DATA_DIR = "/var/local/submitty"

def parse_args():
    parser = argparse.ArgumentParser()
    parser.add_argument("config_file_path")
    parser.add_argument("assignment")
    parser.add_argument("semester")
    parser.add_argument("course")
    return parser.parse_args()

def main():
    args = parse_args()
    complete_config_json_path = os.path.join(SUBMITTY_DATA_DIR,
                                            'courses',
                                            args.semester,
                                            args.course,
                                            'config',
                                            'complete_config',
                                            'complete_config_' + args.assignment + '.json')
                                            
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
        "max_possible_grading_time" : -1,
        "who" : "build",
        "regrade" : False,
    }

    should_generated_output = False
    for testcase in testcases:  
        input_generation_commands = testcase.get('input_generation_commands',[])
        solution_containers = testcase.get('solution_containers',[])
        should_generate_solution = False
        for solution_container in solution_containers:
            if len(solution_container["commands"]) != 0 :
                should_generate_solution = True
                break

        if should_generate_solution and not input_generation_commands:
            path_grading_file = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_queue", "__".join([args.semester, args.course, args.assignment]))
            
            if os.path.isfile(path_grading_file):
                os.remove(path_grading_file)
            
            with open(path_grading_file, 'w') as grading_file:
                json.dump(graded_file, grading_file,sort_keys=True,indent=4)
            print("Starting to build generated output")
            break

if __name__ == "__main__":
    main()