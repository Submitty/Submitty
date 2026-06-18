#!/usr/bin/env python3

"""
# USAGE
# config_syntax_check.py   <path to config file for gradeable>   <assignment>   <semester>  <course>
"""

import argparse
import json
import os
from submitty_utils import submitty_schema_validator
import sys
import traceback

CONFIG_PATH = os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', 'config')
with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
    OPEN_JSON = json.load(open_file)
SUBMITTY_INSTALL_DIR = OPEN_JSON['submitty_install_dir']
SUBMITTY_DATA_DIR = OPEN_JSON['submitty_data_dir']


def parse_args():
    parser = argparse.ArgumentParser()
    parser.add_argument("config_file_path")
    parser.add_argument("assignment")
    parser.add_argument("semester")
    parser.add_argument("course")
    return parser.parse_args()


def main():
    # Parse the argument necessary to find the complete config json.
    args = parse_args()
    # Grab the path to the complete config json for this assignment
    complete_config_json_path = os.path.join(
        SUBMITTY_DATA_DIR,
        'courses',
        args.semester,
        args.course,
        'config',
        'complete_config',
        f'complete_config_{args.assignment}.json'
    )

    # Get the path to the complete config schema.
    complete_config_schema_path = os.path.join(
        SUBMITTY_INSTALL_DIR,
        'bin',
        'json_schemas',
        'complete_config_schema.json'
    )

    # Verify that the two files exist
    for json_path in [complete_config_json_path, complete_config_schema_path]:
        if not os.path.isfile(json_path):
            print(f"Error, the following file is missing on your system: {json_path}")
            sys.exit(1)

    # Run the schema validator, printing an error on failure.
    try:
        submitty_schema_validator.validate_complete_config_schema_using_filenames(
            complete_config_json_path,
            complete_config_schema_path,
            warn=False
        )
    except submitty_schema_validator.SubmittySchemaException as s:
        s.print_human_readable_error()
        print("The submitty configuration validator detected the above error in your config.")
        print("This is a new feature. If you feel that an error was incorrectly identified,")
        print("please submit an error report at https://github.com/Submitty/Submitty")
        print()
    except Exception:
        traceback.print_exc()


if __name__ == '__main__':
    main()
