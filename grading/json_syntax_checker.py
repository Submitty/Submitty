#!/usr/bin/env python3
import argparse
import os
import json
import re
import traceback
import sys
import collections


def detect_duplicate_keys(list_of_pairs):
    """https://gist.github.com/htv2012/ad8c19ac43e128aa7ee1"""
    key_count = collections.Counter(k for k, _ in list_of_pairs)
    duplicate_keys = ', '.join(k for k, v in key_count.items() if v > 1)

    if len(duplicate_keys) != 0:
        print(f"WARNING: Duplicate key(s) found: {duplicate_keys}")

    return dict(list_of_pairs)


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
        output = json.loads(j_string, object_pairs_hook=detect_duplicate_keys)
    except Exception:
        print(f'ERROR: Could not load {args.file}')
        traceback.print_exc()
        sys.exit(1)

    with open(args.file, 'w') as out_file:
        json.dump(output, out_file, indent=4, sort_keys=True)
