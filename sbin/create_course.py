#!/usr/bin/env python3
"""
create_course.py

Python conversion of create_course.sh
"""

import argparse
import json
import os
import sys
import grp
import pwd


# this script must be run by root or sudo
def check_root():
    if os.geteuid() != 0:
        print("ERROR: This script must be run by root or sudo")
        sys.exit(1)


def load_config():
    script_dir = os.path.dirname(os.path.realpath(__file__))
    conf_dir = os.path.join(script_dir, "..", "config")

    def load(filename):
        path = os.path.join(conf_dir, filename)
        with open(path) as f:
            return json.load(f)

    submitty = load("submitty.json")
    submitty_users = load("submitty_users.json")
    database = load("database.json")

    return {
        "submitty_repository": submitty["submitty_repository"],
        "submitty_install_dir": submitty["submitty_install_dir"],
        "submitty_data_dir": submitty["submitty_data_dir"],
        "submission_url": submitty["submission_url"],
        "php_user": submitty_users["php_user"],
        "daemon_user": submitty_users["daemon_user"],
        "cgi_user": submitty_users["cgi_user"],
        "course_builders_group": submitty_users["course_builders_group"],
        "database_host": database["database_host"],
        "database_port": database["database_port"],
        "database_user": database["database_user"],
        "database_password": database["database_password"],
        "database_course_user": database["database_course_user"],
    }


def parse_args():
    parser = argparse.ArgumentParser(
        description="Create a new Submitty course.",
        usage=(
            "%(prog)s [--archive] [--all-self-registration | "
            "--request-self-registration] "
            "<semester> <course> <instructor> <ta_group>"
        ),
    )

    parser.add_argument(
        "--archive",
        action="store_true",
        default=False,
        help="Mark the course as archived immediately after creation.",
    )

    reg_group = parser.add_mutually_exclusive_group()
    reg_group.add_argument(
        "--all-self-registration",
        action="store_const",
        const=2,
        dest="self_registration_type",
        help="Allow all students to self-register.",
    )
    reg_group.add_argument(
        "--request-self-registration",
        action="store_const",
        const=1,
        dest="self_registration_type",
        help="Allow students to request self-registration.",
    )
    parser.set_defaults(self_registration_type=0)

    parser.add_argument("semester", help="Semester identifier")
    parser.add_argument("course", help="Course identifier")
    parser.add_argument("instructor", help="Instructor's system username")
    parser.add_argument("ta_group", help="Group name for course TAs")

    args = parser.parse_args()

    print("\nCREATE COURSE:")
    print(f"  semester:               {args.semester}")
    print(f"  course:                 {args.course}")
    print(f"  instructor:             {args.instructor}")
    print(f"  ta_group:               {args.ta_group}")
    print(f"  archived:               {args.archive}")
    print(f"  self_registration_type: {args.self_registration_type}\n")

    return args


def validate(args, cfg):
    instructor  = args.instructor
    ta_group    = args.ta_group
    php_user    = cfg["php_user"]
    daemon_user = cfg["daemon_user"]
    cgi_user    = cfg["cgi_user"]
    builders    = cfg["course_builders_group"]

    try:
        pwd.getpwnam(instructor)
    except KeyError:
        print(f"ERROR: {instructor} user does not exist\n")
        sys.exit(1)

    try:
        grp.getgrnam(ta_group)
    except KeyError:
        print(f"ERROR: {ta_group} group does not exist\n")
        sys.exit(1)

    def in_group(user, group):
        try:
            g = grp.getgrnam(group)
        except KeyError:
            return False
        user_gid = pwd.getpwnam(user).pw_gid
        return user in g.gr_mem or user_gid == g.gr_gid

    if not in_group(instructor, builders):
        print(f"ERROR: {instructor} is not in group {builders}\n")
        sys.exit(1)

    if not in_group(instructor, ta_group):
        print(f"ERROR: {instructor} is not in group {ta_group}\n")
        sys.exit(1)
    if not in_group(php_user, ta_group):
        print(f"ERROR: {php_user} is not in group {ta_group}\n")
        sys.exit(1)
    if not in_group(daemon_user, ta_group):
        print(f"ERROR: {daemon_user} is not in group {ta_group}\n")
        sys.exit(1)
    if not in_group(cgi_user, ta_group):
        print(f"ERROR: {cgi_user} is not in group {ta_group}\n")
        sys.exit(1)


def main():
    check_root()

    cfg = load_config()
    print(cfg["database_course_user"])

    args = parse_args()

    validate(args, cfg)
    print("All user/group validation checks passed.")


if __name__ == "__main__":
    main()
    