#!/usr/bin/env python3

import argparse
import json
import os

CONFIG_PATH = os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', 'config')

with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
    JSON = json.load(open_file)
INI_PATH = os.path.join(JSON['submitty_install_dir'], 'site/public/hwgrading/toolbox/configs/')
INI_FILE = "student_csv_fields.ini"
with open(os.path.join(CONFIG_PATH, 'submitty_users.json')) as open_file:
    JSON = json.load(open_file)
INI_OWNER = JSON['hwphp_user']

if os.geteuid() != 0:
    raise SystemExit("Only root is allowed to run this script.")


def parse_args():
    parser = argparse.ArgumentParser()
    parser.add_argument("first_name", metavar="FIRST_NAME", type=int)
    parser.add_argument("last_name", metavar="LAST_NAME", type=int)
    parser.add_argument("email", metavar="EMAIL", type=int)
    parser.add_argument("section", metavar="SECTION", type=int)
    return parser.parse_args()


def main():
    args = parse_args()
    if len({args.first_name, args.last_name, args.email, args.section}) != 4:
        raise SystemExit("All passed arguments must be unique.")
    ini_file = os.path.join(INI_PATH, INI_FILE)
    with open(ini_file, "w") as open_file:
        open_file.write("""; This sets the CSV fields from a student class list that relate to course DB
; entries.  Please run 'bin/setcsvfields' to set this configuration.

[student_csv_fields]
student_first_name = {:d}
student_last_name  = {:d}
student_email      = {:d}
student_section    = {:d}""".format(args.first_name, args.last_name, args.email, args.section))

    os.system("chown {}:{} {}".format(INI_OWNER, INI_OWNER, ini_file))
    os.chmod(ini_file, 0o400)

if __name__ == "__main__":
    main()
