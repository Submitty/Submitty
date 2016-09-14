#!/usr/bin/python

"""
Generate a list of active versions for students. Useful for when the database gets bad values for
the active version and then you can just run this function to get the proper values to update the
database with.
"""

from __future__ import print_function
import argparse
import json
import os

# FIXME: Path to the courses directory for submitty. Currently hardcoded pending #607
DATA_PATH = "/var/local/submitty/courses"


def get_actives(semester, course):
    """
    Given a semester and course, generate a list of all students and their active versions for
    all assignments by looking at the user_assignment_settings.json file that is genenerated when a
    a submission is made. If that file does not exist, we assume that no submission was made and
    a value of "-1" is set for the active version for that assignment for that student.

    :param semester: semester to use for the active versions
    :type semester: str
    :param course: course to examine for the active versions
    :type semester: str
    :return: a dictionary containing all students and their active versions for all assignemnts
    for the course and semester
    :rtype: dict
    """
    versions = {}
    submission_path = os.path.join(DATA_PATH, semester, course, "submissions")
    for homework in os.listdir(submission_path):
        homework_path = os.path.join(submission_path, homework)
        for student in os.listdir(homework_path):
            if student not in versions:
                versions[student] = {}
            versions[student][homework] = -1
            with open(os.path.join(homework_path, student, "user_assignment_settings.json"),
                      "r") as read_file:
                json_file = json.load(read_file)
                versions[student][homework] = json_file["active_version"]
    return versions


def main():
    """
    Main program execution. Pretty prints the generated dictionary for students and their active
    versions
    """
    parser = argparse.ArgumentParser(description="Generate a list of students and their active "
                                                 "versions for all assignments")
    parser.add_argument("semester", type=str, help="What semester to look at?")
    parser.add_argument("course", type=str, help="What course to look at?")
    args = parser.parse_args()
    print(json.dumps(get_actives(args.semester, args.course), indent=4))

if __name__ == "__main__":
    main()
