"""
Contains all the global constant variables that are used
throughout the sample courses.
None of the variables should be changed after they are set here.
If you ever want to change the global variables in another file,
you have to use import sample_courses and not from sample_courses import *,
but its highly unrecommended.

To prevent circular imports, don't import any files in this package and if
you need to import something then use import sample_courses.filename not from
sample_courses.filename import *
"""

from ruamel.yaml import YAML
from submitty_utils import dateutils
import argparse
import json

import os


def parse_args():
    """
    Parses out the arguments that might be passed to this script as it's run as a commandline
    application.
    :return: parsed args from the argparse module
    """
    parser = argparse.ArgumentParser(
        description="Sets up the sample courses as well as creating the necessary users for the "
                    "course as needed. It reads in the courses.json and users.json files from the "
                    ".setup/data directory to determine what courses/users are allowed and then "
                    "either adds all or just a few depending on what gets passed to this script")

    parser.add_argument("--db_only", action='store_true', default=False)
    parser.add_argument("--no_submissions", action='store_true', default=False)
    parser.add_argument("--no_grading", action='store_true', default=False)
    parser.add_argument("--test_only_grading", action='store_true', default=False)
    parser.add_argument("--users_path", default=os.path.join(SETUP_DATA_PATH, "users"),
                        help="Path to folder that contains .yml files to use for user creation. "
                        "Defaults to ../data/users")
    parser.add_argument("--submission_url", type=str, default="", help="top level url for the "
                        "website")
    parser.add_argument("--courses_path", default=os.path.join(SETUP_DATA_PATH, "courses"),
                        help="Path to the folder that contains .yml files to use for course "
                        "creation. Defaults to ../data/courses")
    parser.add_argument("--install_dir", type=str, default="/usr/local/submitty",
                        help="install path of submitty")
    parser.add_argument("--data_dir", type=str, default="/var/local/submitty",
                        help="data path of submitty")
    parser.add_argument("course", nargs="*",
                        help="course code to build. If no courses are passed in, then it'll use "
                        "all courses in courses.json")
    return parser.parse_args()


# Start of global variables
yaml = YAML(typ='safe')

CURRENT_PATH = os.path.dirname(os.path.realpath(__file__))
SETUP_DATA_PATH = os.path.join(CURRENT_PATH, "..", "..", "data")

args = parse_args()

SUBMITTY_INSTALL_DIR: str = args.install_dir
SUBMITTY_DATA_DIR: str = args.data_dir
SUBMITTY_REPOSITORY: str = os.path.join(SUBMITTY_INSTALL_DIR, "GIT_CHECKOUT/Submitty")
MORE_EXAMPLES_DIR: str = os.path.join(SUBMITTY_INSTALL_DIR, "more_autograding_examples")
TUTORIAL_DIR: str = os.path.join(SUBMITTY_INSTALL_DIR, "GIT_CHECKOUT/Tutorial", "examples")

DB_HOST: str = "localhost"
DB_PORT: int = 5432
DB_USER: str = "submitty_dbuser"
DB_PASS: str = "submitty_dbuser"

if not os.path.isdir(SUBMITTY_INSTALL_DIR):
    raise SystemError(f"The following directory does not exist: {SUBMITTY_INSTALL_DIR}")
if not os.path.isdir(SUBMITTY_DATA_DIR):
    raise SystemError(f"The following directory does not exist: {SUBMITTY_DATA_DIR}")
for directory in ["courses"]:
    if not os.path.isdir(os.path.join(SUBMITTY_DATA_DIR, directory)):
        raise SystemError("The following directory does not exist: " + os.path.join(
            SUBMITTY_DATA_DIR, directory))

with open(os.path.join(SUBMITTY_INSTALL_DIR, "config", "database.json")) as database_config:
    database_config_json = json.load(database_config)

    DB_USER = database_config_json["database_user"]
    DB_HOST = database_config_json["database_host"]
    DB_PORT = database_config_json["database_port"]
    DB_PASS = database_config_json["database_password"]

# used for constructing the url to clone repos for vcs gradeables
# with open(os.path.join(SUBMITTY_INSTALL_DIR, "config", "submitty.json")) as submitty_config:
#     submitty_config_json = json.load(submitty_config)
SUBMISSION_URL: str = 'submitty_config_json["submission_url"]'
VCS_FOLDER: str = os.path.join(SUBMITTY_DATA_DIR, 'vcs', 'git')

DB_ONLY: bool = args.db_only
NO_SUBMISSIONS: bool = args.no_submissions
NO_GRADING: bool = args.no_grading
TEST_ONLY_GRADING: bool = args.test_only_grading
NOW = dateutils.get_current_time()
