#!/usr/bin/env python

from __future__ import print_function
import argparse
from datetime import datetime
import json
import os

SUBMITTY_INSTALL_DIR = "__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__"
SUBMITTY_DATA_DIR = "__INSTALL__FILLIN__SUBMITTY_DATA_DIR__"

HWPHP_USER = "__INSTALL__FILLIN__HWPHP_USER__"
HWCRON_USER = "__INSTALL__FILLIN__HWCRON_USER__"

HWPHP_UID = "__INSTALL__FILLIN__HWPHP_UID__"

if os.getuid() != HWPHP_UID:
    raise SystemError("ERROR: this script must be run by {}".format(HWPHP_USER))


def parse_args():
    parser = argparse.ArgumentParser()
    parser.add_argument("semester")
    parser.add_argument("course")
    parser.add_argument("user")
    parser.add_argument("gradeable")

    return parser.parse_args()


def main():
    args = parse_args()

    submissions_dir = os.path.join(SUBMITTY_DATA_DIR, "courses", args.semester, args.course, "submissions")
    config_file = os.path.join(SUBMITTY_DATA_DIR, "courses", args.semester, args.course, "config", "build",
                               "build_{}.json".format(args.gradeable))

    if not os.path.isdir(submissions_dir):
        raise SystemError("ERROR: specific course submissions {} does not exist!".format(submissions_dir))

    if not os.path.isfile(config_file):
        raise SystemError("ERROR: specific homework configuration file {} does not exist!".format(config_file))

    os.system("mkdir -p {}".format(os.path.join(submissions_dir, args.gradeable)))
    os.system("chmod o-rwx {}".format(os.path.join(submissions_dir, args.gradeable)))

    # make the user directory (if needed)
    os.system("mkdir -p {}/{}/{}".format(submissions_dir, args.gradeable, args.user))
    os.system("chmod o-rwx {}/{}/{}".format(submissions_dir, args.gradeable, args.user))

    version = len(os.listdir(os.path.join(submissions_dir, args.gradeable, args.user))) + 1
    print("creating submission: {}".format(os.path.join(submissions_dir, args.gradeable, args.user, version)))
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print("timestamp is {}".format(timestamp))

    os.system("mkdir -p {}".format(os.path.join(submissions_dir, args.gradeable, args.user, version)))
    os.system("chmod o-rwx {}".format(os.path.join(submissions_dir, args.gradeable, args.user, version)))

    with open(os.path.join(submissions_dir, args.gradeable, args.user, version, ".submit.timestamp"), "w") as open_file:
        open_file.write(timestamp)

    open(os.path.join(submissions_dir, args.gradeable, args.user, version, ".submit.SVN_CHECKOUT")).close()

    assignment = os.path.join(submissions_dir, args.gradeable, args.user, "user_assignment_settings.json")
    history = []
    if os.path.isfile(assignment):
        assignment_json = json.load(assignment)
        history = assignment_json['history']
    history.append({"version": version, "time": timestamp})
    with open(assignment, "w") as open_file:
        json.dump({"active_version": version, "history": history}, open_file)

if __name__ == "__main__":
    main()
