#!/usr/bin/env python3
import argparse
import os
import json

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="JSON validator to ensure that a JSON is of proper format")
    parser.add_argument("file", metavar="file_name", type=str,
                        help="File name of JSON file to validate")
    args = parser.parse_args()
    if not os.path.isfile(args.file):
        raise SystemExit("Cannot find JSON file '%s' to validate" % args.file)
    with open(args.file, "r") as json_file:
        try:
            json.load(json_file)
        except ValueError as e:
            raise SystemExit(e)
