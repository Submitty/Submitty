from __future__ import print_function
import argparse
import json
import os

DATA_PATH = "/var/local/submitty/courses"


def get_actives(semester, course):
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
    print(json.dumps(versions, indent=4))


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("semester", type=str, help="What semester to look at?")
    parser.add_argument("course", type=str, help="What course to look at?")
    args = parser.parse_args()
    get_actives(args.semester, args.course)
