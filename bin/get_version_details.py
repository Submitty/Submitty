#!/usr/bin/env python3

"""
Generate a list of active versions for students. Useful for when the database gets bad values for
the active version and then you can just run this function to get the proper values to update the
database with.

Basic usage of this script is ./get_version_details.py <semester> <course> which will get all
students and their submissions for that particular semester course. You can also pass in
optional flag to not have any indentation in the JSON produced as well as to write the JSON
to a file instead of printing it out to stdout.

To see all options, use ./get_version_details.py --help
"""

import argparse
from datetime import datetime
import json
import os

DATA_PATH = os.path.join("__INSTALL__FILLIN__SUBMITTY_DATA_DIR__", "courses")


def get_all_versions(semester, course):
    """
    Given a semester and course, generate a dictionary of every student, that then
    contains every homework for that student and then all versions (with condensed
    details) for the version. Additionally, we mark one of these versions as "active" based on the
    value in their user_assignment_settings.json file.

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
        buildfile = os.path.join(build_path, "build_" + homework + ".json")
        if os.path.isfile(buildfile):
            with open(buildfile) as open_file:
                parsed = json.load(open_file)
                if parsed['testcases']:
                    for testcase in parsed['testcases']:
                        testcases.append({"title": testcase['title'],
                                          "points": testcase['points'],
                                          "extra_credit": testcase['extra_credit'],
                                          "hidden": testcase['hidden']})
        homework_path = os.path.join(submission_path, homework)
        for student in os.listdir(homework_path):
            if student not in versions:
                versions[student] = {}
            versions[student][homework] = {}
            with open(os.path.join(homework_path, student, "user_assignment_settings.json"),
                      "r") as read_file:
                json_file = json.load(read_file)
                active = int(json_file["active_version"])
            results_student = os.path.join(results_path, homework, student)
            for version in sorted(os.listdir(results_student)):
                versions[student][homework][version] = get_version_details(semester, course,
                                                                           homework, student,
                                                                           version, testcases,
                                                                           active)
    return versions


def get_version_details(semester, course, homework, student, version, testcases, active_version):
    results_path = os.path.join(DATA_PATH, semester, course, "results", homework, student, version)
    entry = {'autograding_non_hidden_non_extra_credit': 0,
             'autograding_non_hidden_extra_credit': 0,
             'autograding_hidden_non_extra_credit': 0,
             'autograding_hidden_extra_credit': 0,
             'submission_time': None, 'active': False}
    if int(version) == active_version:
        entry['active'] = True
    results_json = os.path.join(results_path, "results.json")
    if not os.path.isfile(results_json):
        return False
    with open(results_json) as open_file:
        open_file = json.load(open_file)
        if testcases:
            if len(testcases) != len(open_file['testcases']):
                return False
            for i in range(len(open_file['testcases'])):
                points = float(open_file['testcases'][i]['points_awarded'])
                hidden = "hidden" if testcases[i]['hidden'] else "non_hidden"
                ec = "extra_credit" if testcases[i]['extra_credit'] else "non_extra_credit"
                entry['autograding_' + hidden + "_" + ec] += points
        with open(os.path.join(results_path, "history.json")) as open_file:
            json_file = json.load(open_file)
        if isinstance(json_file, list):
            a = datetime.strptime(json_file[-1]['submission_time'], "%a %b  %d %H:%M:%S %Z %Y")
            entry['submission_time'] = '{}-{:02d}-{:02d} {:02d}:{:02d}:{:02d}'\
                .format(a.year, a.month, a.day, a.hour, a.minute, a.second)
    return entry


def main():
    """
    Main program execution. Pretty prints the generated dictionary for students and their active
    versions
    """
    parser = argparse.ArgumentParser(description="Generate a list of students and their version "
                                                 "details for all assignments")
    parser.add_argument("semester", type=str, help="What semester to look at?")
    parser.add_argument("course", type=str, help="What course to look at?")
    parser.add_argument("-n", "--no-indent", dest="no_indent", action="store_true", default=False)
    parser.add_argument("-o", "--outfile", dest="outfile", type=str, default=None)
    args = parser.parse_args()
    versions = get_all_versions(args.semester, args.course)
    indent = None
    if not args.no_indent:
        indent = 4
    if args.outfile:
        with open(args.outfile, 'w') as open_file:
            json.dump(versions, open_file, indent=indent)
    else:
        print(json.dumps(versions, indent=indent))


if __name__ == "__main__":
    main()
