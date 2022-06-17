#!/usr/bin/env python3
import argparse
import os
import json
import re
import traceback
import sys

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Preprocess a instructor config json to prepare it for main_configure.cpp")
    parser.add_argument("file", metavar="file_name", type=str,
                        help="File name of JSON file to validate")
    args = parser.parse_args()
    if not os.path.isfile(args.file):
        raise SystemExit(f"Cannot find JSON file '{args.file}' to validate")

    with open(args.file, "r") as input_file:
        j_string = input_file.read()

    # Remove cpp # markers
    j_string = re.sub("(^|\n)#[^\n]*(?=\n)", "", j_string)

    # Attempt to load the instructor json.
    try:
        output = json.loads(j_string)
    except Exception:
        traceback.print_exc()
        print(f'ERROR: Could not load {args.file}')
        sys.exit(1)

    with open(args.file, 'w') as out_file:
        json.dump(output, out_file, indent=4, sort_keys=True)
