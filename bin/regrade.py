#!/usr/bin/env python3
"""

Expected directory structure:
<BASE_PATH>/courses/<SEMESTER>/<COURSES>/submissions/<HW>/<USERNAME>/<VERSION#>

This script will find all submissions that match the provided
pattern and add them to the grading queue.

USAGE:
    regrade.sh  <(absolute or relative) PATTERN PATH>
    regrade.sh  <(absolute or relative) PATTERN PATH>  interactive
"""

import argparse
import json
import os
import sys

SUBMITTY_DATA_DIR = "__INSTALL__FILLIN__SUBMITTY_DATA_DIR__"


class StoreQueue(argparse.Action):
    def __call__(self, parser, args, values, option_string=None):
        if values.lower() not in ["interactive", "batch"]:
            raise SystemExit("Invalid choice: '{}' (choose from 'INTERACTIVE' or 'BATCH')".format(values))
        setattr(args, self.dest, values.lower())


def arg_parse():
    parser = argparse.ArgumentParser(description="Re-adds any submission folders found in the given path and adds"
                                                 "them to a queue (default batch) for regrading")
    parser.add_argument("path", metavar="PATH", help="Path (absolute or relative) to submissions to regrade")
    parser.add_argument("queue", nargs="?", metavar="QUEUE", default="batch",
                        action=StoreQueue, help="What queue (INTERACTIVE or BATCH) to use for the regrading. Default "
                                                "is batch.")
    return parser.parse_args()


def check_path(check, user_path):
    if not os.path.isdir(check):
        return False
    return check == user_path[:len(check)] if len(check) <= len(user_path) else check[:len(user_path)] == user_path


def main():
    args = arg_parse()
    input_path = os.path.abspath(args.path)
    data_dir = os.path.join(SUBMITTY_DATA_DIR, "courses")
    grade_queue = []
    if not os.path.isdir(input_path) or data_dir not in input_path:
        raise SystemExit("You need to point to a directory within {}".format(data_dir))
    for semester in os.listdir(data_dir):
        semester_path = os.path.join(data_dir, semester)
        if not check_path(semester_path, input_path):
            continue
        print("Matching semester: {}".format(semester))
        for course in os.listdir(semester_path):
            course_path = os.path.join(semester_path, course)
            submissions_path = os.path.join(course_path, "submissions")
            if not check_path(submissions_path, input_path):
                continue
            print("Matching course: {}".format(course))
            for assignment in os.listdir(submissions_path):
                assignment_path = os.path.join(submissions_path, assignment)
                if not check_path(assignment_path, input_path):
                    continue
                print("Matching assignment: {}".format(assignment))
                for user in os.listdir(assignment_path):
                    user_path = os.path.join(assignment_path, user)
                    if not check_path(user_path, input_path):
                        continue
                    print("Matching user: {}".format(user))
                    for version in os.listdir(user_path):
                        version_path = os.path.join(user_path, version)
                        if not check_path(version_path, input_path) or version in ["ACTIVE", "LAST"]:
                            continue
                        grade_queue.append({"semester": semester, "course": course, "assignment": assignment,
                                            "user": user, "version": version})
                        print("Grade this: {}".format("__".join([semester, course, assignment, user, version])))

    if len(grade_queue) > 50:
        inp = input("Found {:d} matching submissions. Add to queue? [y/n]".format(len(grade_queue)))
        if inp.lower() not in ["yes", "y"]:
            raise SystemExit("Aborting...")

    for item in grade_queue:
        file_name = "__".join([item['semester'], item['course'], item['assignment'], item['user'], item['version']])
        file_name = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_" + args.queue, file_name)
        with open(file_name, "w") as open_file:
            json.dump(item, open_file)
        os.system("chmod o+rw {}".format(file_name))

    print("Added {:d} to the {} queue for regrading.".format(len(grade_queue), args.queue.upper()))


if __name__ == "__main__":
    main()
