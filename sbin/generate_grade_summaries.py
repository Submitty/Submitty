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
    data_dir = data['submitty_data_dir']

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
        description='Automatically call APIs to load and save GUI customization and generate grade summaries.'
    )
    parser.add_argument('semester')
    parser.add_argument('course')
    args = parser.parse_args()

    semester = args.semester
    course = args.course
    token = creds['token']

    """Automatically call API to generate and save GUI customization."""
    try:

        customization_file = os.path.join(data_dir, 'courses', semester, course, 'rainbow_grades', 'customization.json')
        if not os.path.exists(customization_file):
            raise Exception('Unable to locate customization.json file')
        with open(customization_file, 'r') as file:
            customization_data = json.load(file)

        # API calls to load customization page
        load_response = requests.post('{}/api/courses/{}/{}/reports/rainbow_grades_customization'.format(
                base_url, semester, course
            ),
            headers={'Authorization': token},
            data={"json_string": json.dumps(customization_data)}
        )
        save_response = requests.post(
            '{}/api/courses/{}/{}/reports/rainbow_grades_customization_save'.format(
                base_url, semester, course
            ),
            headers={'Authorization': token},
            data={"json_string": json.dumps(customization_data)}
        )
    except Exception:
        print("ERROR: Invalid arguments.", file=stderr)
        exit(-1)

    if load_response.status_code == 200 and save_response.status_code == 200:
        load_response = load_response.json()
        save_response = save_response.json()
        if load_response["status"] == 'success' and save_response["status"] == 'success':
            print("Successfully loaded and saved Rainbow Grades for {}.{}".format(
                semester, course
            ))
        else:
            print("ERROR: Failed to load and save Rainbow Grades for {}.{}.".format(
                semester, course
            ), file=stderr)
            print("Reason:{}".format(
                load_response["message"] + " " + save_response["message"]
            ), file=stderr)
    else:
        print("ERROR: Submitty Service Unavailable.", file=stderr)

    """Automatically call Generate Grade Summaries API"""
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
