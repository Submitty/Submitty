#!/usr/bin/env python3
"""
Script to trigger the generate grade summaries.

Usage:
./generate_grade_summaries.py <semester> <course>
"""

import argparse
import requests
import os
import json
from sys import stderr

# Get path to current file directory
current_dir = os.path.dirname(__file__)

# Collect submission url
submitty_json_config = os.path.join(current_dir, '..', 'config', 'submitty.json')

if not os.path.exists(submitty_json_config):
    raise Exception('Unable to locate submitty.json configuration file')

with open(submitty_json_config, 'r') as file:
    data = json.load(file)
    base_url = data['submission_url'].rstrip('/')
    install_dir = data['submitty_install_dir']

# Collect submitty admin token
submitty_creds_file = os.path.join(install_dir, 'config', 'submitty_admin.json')

if not os.path.exists(submitty_creds_file):
    raise Exception('Unable to locate submitty_admin.json credentials file')

# Load credentials out of admin file
with open(submitty_creds_file, 'r') as file:
    creds = json.load(file)

if 'token' not in creds or not creds['token']:
    raise Exception('Unable to read credentials from submitty_admin.json')


def main():
    """Automatically call Generate Grade Summaries API."""
    parser = argparse.ArgumentParser(
        description='Automatically call Generate Grade Summaries API.'
    )
    parser.add_argument('semester')
    parser.add_argument('course')
    args = parser.parse_args()

    semester = args.semester
    course = args.course
    token = creds['token']

    try:
        grade_generation_response = requests.post(
            '{}/api/courses/{}/{}/reports/summaries'.format(
                base_url, semester, course
            ),
            headers={'Authorization': token}
        )
    except Exception:
        print("ERROR: Invalid arguments.", file=stderr)
        exit(-1)

    if grade_generation_response.status_code == 200:
        grade_generation_response = grade_generation_response.json()
        if grade_generation_response["status"] == 'success':
            print("Successfully generated grade summaries for {}.{}".format(
                semester, course
            ))
        else:
            print("ERROR: Failed to generate grade summaries for {}.{}.".format(
                semester, course
            ), file=stderr)
            print("Reason:{}".format(
                grade_generation_response["message"]
            ), file=stderr)
    else:
        print("ERROR: Submitty Service Unavailable.", file=stderr)


if __name__ == "__main__":
    main()
