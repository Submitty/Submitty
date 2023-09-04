from ruamel.yaml import YAML
from submitty_utils import dateutils
import argparse

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
    parser.add_argument("--users_path", default=os.path.join(SETUP_DATA_PATH, "users"),
                        help="Path to folder that contains .yml files to use for user creation. Defaults to "
                             "../data/users")
    parser.add_argument("--submission_url", type=str, default="",help="top level url for the website")
    parser.add_argument("--courses_path", default=os.path.join(SETUP_DATA_PATH, "courses"),
                        help="Path to the folder that contains .yml files to use for course creation. Defaults to "
                             "../data/courses")
    parser.add_argument("--install_dir", type=str, default=SUBMITTY_INSTALL_DIR, help="install path of submitty")
    parser.add_argument("--data_dir", type=str, default=SUBMITTY_DATA_DIR, help="data path of submitty")
    parser.add_argument("course", nargs="*",
                        help="course code to build. If no courses are passed in, then it'll use "
                             "all courses in courses.json")
    return parser.parse_args()

# Start of global variables
args = parse_args()
yaml = YAML(typ='safe')

CURRENT_PATH = os.path.dirname(os.path.realpath(__file__))
SETUP_DATA_PATH = os.path.join(CURRENT_PATH, "..", "data")

# Default values, will be overwritten in `main()` if corresponding arguments are supplied
SUBMITTY_INSTALL_DIR = args.install_dir
SUBMITTY_DATA_DIR = args.data_dir
SUBMITTY_REPOSITORY = os.path.join(SUBMITTY_INSTALL_DIR, "GIT_CHECKOUT/Submitty")
MORE_EXAMPLES_DIR = os.path.join(SUBMITTY_INSTALL_DIR, "more_autograding_examples")
TUTORIAL_DIR = os.path.join(SUBMITTY_INSTALL_DIR, "GIT_CHECKOUT/Tutorial", "examples")

DB_HOST = "localhost"
DB_PORT = 5432
DB_USER = "submitty_dbuser"
DB_PASS = "submitty_dbuser"

# used for constructing the url to clone repos for vcs gradeables
# with open(os.path.join(SUBMITTY_INSTALL_DIR, "config", "submitty.json")) as submitty_config:
#     submitty_config_json = json.load(submitty_config)
SUBMISSION_URL = 'submitty_config_json["submission_url"]'
VCS_FOLDER = os.path.join(SUBMITTY_DATA_DIR, 'vcs', 'git')

DB_ONLY = args.db_only
NO_SUBMISSIONS = args.no_submissions
NO_GRADING = args.no_grading

NOW = dateutils.get_current_time()