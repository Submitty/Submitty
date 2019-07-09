#!/usr/bin/env python3
"""
Script to trigger the generate grade summaries.

Usage:
./generate_grade_summaries.py <semester> <course> <base_url> <token>
"""

import argparse
import requests
from sys import stderr


def main():
    """Automatically call Generate Grade Summaries API."""
    parser = argparse.ArgumentParser(
        description='Automatically call Generate Grade Summaries API.'
    )
    parser.add_argument('semester')
    parser.add_argument('course')
    parser.add_argument('base_url')
    parser.add_argument('token')
    args = parser.parse_args()

    semester = args.semester
    course = args.course
    base_url = args.base_url.rstrip('/')
    token = args.token

    try:
        grade_generation_response = requests.post(
            '{}/api/{}/{}/reports/summaries'.format(
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
            print("Successfully generated grade reports for {}.{}".format(
                semester, course
            ))
        else:
            print("ERROR: Failed to generate grade reports for {}.{}.".format(
                semester, course
            ), file=stderr)
            print("Reason:{}".format(
                grade_generation_response["message"]
            ), file=stderr)
    else:
        print("ERROR: Submitty Service Unavailable.", file=stderr)


if __name__ == "__main__":
    main()
