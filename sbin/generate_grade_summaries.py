#!/usr/bin/env python3
"""
Script to trigger the generation of grade summaries. Additionally, if <source> is "submitty_daemon", it will
save the latest GUI customization file, crucial for courses not using manual customizations, before submitting
the build process, ensuring Rainbow Grades Summaries are up to date for all users on the site.

Usage:
./generate_grade_summaries.py <semester> <course> <source>
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


def get_error_message(response):
    """Extracts the error message from the response."""
    if 'application/json' in response.headers.get('Content-Type', ''):
        return response.json()['message']
    else:
        return response.text


def save_and_build_rainbow_grades(semester, course, token):
    """Saves the latest GUI customization file, if applicable, and submits the build process"""
    option = "gui"
    save_response = requests.post(
        '{}/api/courses/{}/{}/reports/rainbow_grades_customization_save'.format(
            base_url, semester, course
        ),
        headers={'Authorization': token},
        data={'nightly_save': True}
    )

    if save_response.status_code == 200 and save_response.json()['status'] == 'success':
        print("Successfully saved Rainbow Grades GUI customization for {}.{}".format(
            semester, course
        ))
    else:
        message = save_response.json()['message']

        if message == 'Manual customization is currently in use.':
            option = "manual"
        else:
            print("ERROR: Failed to save Rainbow Grades GUI customization for {}.{} - {}".format(
                semester, course, message
            ), file=stderr)
            exit(-1)

    # Submit the build process
    select_response = requests.post(
            '{}/api/courses/{}/{}/reports/rainbow_grades_customization/manual_or_gui'.format(
                base_url, semester, course
            ),
            headers={'Authorization': token},
            data={
                "selected_value": option,
                "source": "submitty_daemon"
            }
        )

    if select_response.status_code == 200 and select_response.json()['status'] == 'success':
        print("Successfully selected the {} customization for {}.{}".format(
            option, semester, course
        ))
    else:
        print("ERROR: Failed to select the {} customization for {}.{} - {}".format(
            option, semester, course, get_error_message(select_response)
        ), file=stderr)
        exit(-1)

    build_response = requests.post(
        '{}/api/courses/{}/{}/reports/build_form'.format(
            base_url, semester, course
        ),
        headers={
            'Authorization': token,
            'source': 'submitty_daemon'
        }
    )

    if build_response.status_code == 200 and build_response.json()['status'] == 'success':
        print("Successfully submitted the Rainbow Grades build process for {}.{}".format(
            semester, course
        ))
    else:
        print("ERROR: Failed to submit the Rainbow Grades build process for {}.{} - {}".format(
            semester, course, get_error_message(build_response)
        ), file=stderr)
        exit(-1)

    # Remain blocked until the build process is complete and output the final status
    status_response = requests.post(
        '{}/api/courses/{}/{}/reports/rainbow_grades_status'.format(
            base_url, semester, course
        ),
        headers={'Authorization': token}
    )
    print("Successfully completed the Rainbow Grades build process for {}.{} - {}".format(
        semester, course, status_response.json()
    ))


def generate_grade_summaries(semester, course, token):
    """Generates grade summaries for the given course."""
    try:
        grade_generation_response = requests.post(
            '{}/api/courses/{}/{}/reports/summaries'.format(
                base_url, semester, course
            ),
            headers={'Authorization': token}
        )

        if grade_generation_response.status_code == 200:
            grade_generation_response = grade_generation_response.json()

            if grade_generation_response["status"] == 'success':
                print("Successfully generated grade summaries for {}.{}".format(
                    semester, course
                ))
            else:
                print("ERROR: Failed to generate grade summaries for {}.{} - {}".format(
                    semester, course, get_error_message(grade_generation_response)
                ), file=stderr)
        else:
            print("ERROR: Submitty Service Unavailable.", file=stderr)
    except Exception as grade_generation_exception:
        print("ERROR: Failed to generate grade summaries for {}.{} - {}".format(
            semester, course, grade_generation_exception
        ), file=stderr)
        exit(-1)


def main():
    parser = argparse.ArgumentParser(
        description='Automatically call API endpoints to save/load GUI customizations and generate Grade Summaries.'
    )
    parser.add_argument('semester')
    parser.add_argument('course')
    parser.add_argument('source')
    args = parser.parse_args()

    semester = args.semester
    course = args.course
    token = creds['token']
    source = args.source

    if source == 'submitty_daemon':
        # Only save and build Rainbow Grades if the source is the daemon user
        save_and_build_rainbow_grades(semester, course, token)

    # Always generate Rainbow Grades grade summaries
    generate_grade_summaries(semester, course, token)


if __name__ == "__main__":
    main()
