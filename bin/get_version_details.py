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
import time

# FIXME: Path to the courses directory for submitty. Currently hardcoded pending #607
DATA_PATH = "/var/local/submitty/courses"


def get_version_details(semester, course):
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
    results_path = os.path.join(DATA_PATH, semester, course, "results")
    build_path = os.path.join(DATA_PATH, semester, course, "config", "build")
    if not os.path.isdir(submission_path) or not os.path.isdir(results_path):
        raise SystemError("Could not find submission or results directory")
    for homework in os.listdir(submission_path):
        testcases = []
        with open(os.path.join(build_path, "build_" + homework + ".json")) as open_file:
            parsed = json.load(open_file)
            for testcase in parsed['testcases']:
                testcases.append({"title": testcase['title'], "points": testcase['points'],
                                  "extra_credit": testcase['extra_credit']})
        homework_path = os.path.join(submission_path, homework)
        for student in os.listdir(homework_path):
            if student not in versions:
                versions[student] = {}
            versions[student][homework] = {}
            with open(os.path.join(homework_path, student, "user_assignment_settings.json"),
                      "r") as read_file:
                json_file = json.load(read_file)
                active = json_file["active_version"]
            results_student = os.path.join(results_path, homework, student)
            for dire in sorted(os.listdir(results_student)):
                entry = {'autograding_total': 0, 'autograding_extra_credit': 0,
                         'submission_time': None, 'active': False}
                if int(dire) == active:
                    entry['active'] = True
                with open(os.path.join(results_student, dire, "results.json")) as open_file:
                    open_file = json.load(open_file)
                    for i in range(len(open_file['testcases'])):
                        points = open_file['testcases'][i]['points_awarded']
                        if testcases[i]['extra_credit']:
                            entry['autograding_extra_credit'] += points
                        else:
                            entry['autograding_total'] += points
                with open(os.path.join(results_student, dire, "results_history.json")) as open_file:
                    json_file = json.load(open_file)
                    if isinstance(json_file, list):
                        json_file = json_file[0]
                        a = time.strptime(json_file['submission_time'], "%a %b  %d %H:%M:%S %Z %Y")
                        entry['submission_time'] = '{:02d}/{:02d}/{:02d} {:02d}:{:02d}:{:02d}'.\
                            format(a[2], a[1], a[0], a[3], a[4], a[5])
                versions[student][homework][dire] = entry
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
    print(json.dumps(get_version_details(args.semester, args.course), indent=4))

if __name__ == "__main__":
    main()
