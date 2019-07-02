#!/usr/bin/env python3
"""
Script to trigger the generate grade summaries.

Usage:
./generate_grade_summaries.py <base_url> <token>
"""

import argparse
import requests


def main():
    """Automatically call Generate Grade Summaries API."""
    parser = argparse.ArgumentParser(
        description='Automatically call Generate Grade Summaries API.'
    )
    parser.add_argument('base_url')
    parser.add_argument('token')
    args = parser.parse_args()

    base_url = args.base_url.rstrip('/')
    token = args.token

    courses = list()
    course_response = requests.get(
        '{}/api/courses'.format(base_url),
        headers={'Authorization': token}
    )
    if course_response.status_code == 200:
        course_response = course_response.json()
        if course_response["status"] == 'success':
            courses = course_response['data']['unarchived_courses']
        else:
            print("ERROR: {}".format(course_response["message"]))
            exit(-1)
    else:
        print("ERROR: Submitty Service Unavailable.")
        exit(-1)

    for course in courses:
        grade_generation_response = requests.post(
            '{}/api/{}/{}/reports/summaries'.format(
                base_url, course['semester'], course['title']
            ),
            headers={'Authorization': token}
        )
        if grade_generation_response.status_code == 200:
            grade_generation_response = grade_generation_response.json()
            if grade_generation_response["status"] == 'success':
                print("Successfully generated rainbow grades for {}.{}".format(
                    course['semester'], course['title']
                ))
            else:
                print("ERROR: Failed to generate rainbow grades for {}.{}.").format(
                    course['semester'], course['title'],
                ))
                print("Reason:{}".format(grade_generation_response["message"]))
        else:
            print("ERROR: Submitty Service Unavailable.")


if __name__ == "__main__":
    main()
