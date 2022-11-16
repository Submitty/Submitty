import os
import json
import shutil
import unittest
import contextlib
import copy
import pytest

import submitty_autograding_shipper as shipper
from autograder import config

SCRIPT_DIR = os.path.dirname(os.path.realpath(__file__))
# Path any auxiliary test files (e.g. autograding configs)
TEST_DATA_SRC_DIR = os.path.join(SCRIPT_DIR, 'data')

# Where to dump intermediate test files
# The GIT_CHECKOUT folder is guaranteed to be within the Submitty install dir.
WORKING_DIR = os.path.abspath(
    os.path.join('..', '..', '..', 'test_suite', 'unitTests', 'autograder')
)
# Path to place our temporary data files
TEST_DATA_DIR = os.path.join(WORKING_DIR, 'data')
# Equivalent to install dir
TEST_ENVIRONMENT = os.path.join(WORKING_DIR, 'test_environment')

# Holds all system config files (equivalent to /usr/local/submitty/config)
CONFIG_DIR = os.path.join(TEST_ENVIRONMENT, 'config')
SUBMITTY_DATA_DIR = os.path.join(TEST_ENVIRONMENT, 'autograding')

# Autograding directories
TODO_DIR = os.path.join(SUBMITTY_DATA_DIR, 'autograding_TODO')
DONE_DIR = os.path.join(SUBMITTY_DATA_DIR, 'autograding_DONE')
TO_BE_GRADED = os.path.join(SUBMITTY_DATA_DIR, 'to_be_graded_queue')
GRADING = os.path.join(SUBMITTY_DATA_DIR, "in_progress_grading")

# Log directorories
LOG_PATH = os.path.join(TEST_ENVIRONMENT, 'logs')
STACK_TRACES = os.path.join(LOG_PATH, 'autograding_stack_traces')
AUTOGRADING_LOGS = os.path.join(LOG_PATH, 'autograding')
# The autograder.Config object to pass to the shipper.
CONFIG = None


class TestAutogradingShipper(unittest.TestCase):
    """Unittest TestCase."""

    @classmethod
    def tearDownClass(cls):
        """ Tear down the mock environment for these testcases. """
        # Remove the test environment.
        # with contextlib.suppress(FileNotFoundError):
        #     shutil.rmtree(WORKING_DIR)
        pass

    @classmethod
    def setUpClass(cls):
        """
        Sets up a mock environment roughly equivalent to the production server.
        As more features are needed, they should be added here
        """
        global CONFIG

        # Remove the test environment if it is left over from a previous run.
        with contextlib.suppress(FileNotFoundError):
            shutil.rmtree(TEST_ENVIRONMENT)

        # Make the working directory
        os.makedirs(WORKING_DIR, exist_ok=True)

        # Copy test data into the dir
        shutil.rmtree(TEST_DATA_DIR)
        shutil.copytree(TEST_DATA_SRC_DIR, TEST_DATA_DIR)#, dirs_exist_ok=True)

        # All testing will take place within the TEST_ENVIRONMENT directory
        os.mkdir(TEST_ENVIRONMENT)

        # A mock of /usr/local/submitty/config
        os.mkdir(CONFIG_DIR)

        # A mock directory for /var/local/submitty
        os.mkdir(SUBMITTY_DATA_DIR)
        for directory in [TODO_DIR, DONE_DIR, TO_BE_GRADED, GRADING]:
            os.mkdir(directory)

        # A mock directory for /var/local/submitty/logs
        os.mkdir(LOG_PATH)
        for directory in [STACK_TRACES, AUTOGRADING_LOGS]:
            os.mkdir(directory)

        # Create the configuration json files
        submitty_json = {
            'submitty_data_dir': SUBMITTY_DATA_DIR,
            'submitty_install_dir': TEST_ENVIRONMENT,
            'autograding_log_path': AUTOGRADING_LOGS,
            'site_log_path': LOG_PATH,
            'submission_url': '/fake/url/for/submission/',
            'vcs_url': '/fake/url/for/vcs/submission/'
        }
        users_json = {
            # Pretend that we are the daemon user.
            'daemon_uid': os.getuid()
        }
        # The database json is required by autograder/insert_database_version_data.py
        # When we test that script, a mock database may be needed, and these
        # values will have to be updated.
        database_json = {
            'database_user': 'foo',
            'database_host': 'bar',
            'database_password': 'password'
        }

        for filename, json_file in [
            ('submitty', submitty_json),
            ('submitty_users', users_json),
            ('database', database_json)
        ]:
            with open(os.path.join(CONFIG_DIR, f'{filename}.json'), 'w') as outfile:
                json.dump(json_file, outfile, indent=4)

        # Instantiate the shipper's config object
        CONFIG = config.Config.path_constructor(CONFIG_DIR, 'TEST')
        shipper.instantiate_global_variables(CONFIG)

    # def test_can_short_no_testcases(self):
    #     """ We should be able to short circuit configs with no testcases  """
    #     autograding_config = {
    #         "testcases": []
    #     }
    #     self.assertTrue(shipper.can_short_circuit(autograding_config))

    # def test_can_short_circuit_max_submission(self):
    #     """ We should be able to short circuit if the only testcase is max_submission """
    #     with open(os.path.join(TEST_DATA_DIR, 'complete_config_upload_only.json')) as infile:
    #         autograding_config = json.load(infile)
    #     self.assertTrue(shipper.can_short_circuit(autograding_config))

    # def test_cannot_short_circuit_single_non_file_submission_testcase(self):
    #     """
    #     If there is only one testcase, but it is non-file submission, we cannot short circuit.
    #     """
    #     with open(os.path.join(TEST_DATA_DIR, 'complete_config_cpp_cats.json')) as infile:
    #         tmp_config = json.load(infile)
    #     # Create a config that is a copy of cpp cats but with only one testcase.
    #     autograding_config = copy.deepcopy(tmp_config)
    #     autograding_config['testcases'] = []
    #     autograding_config['testcases'].append(tmp_config['testcases'][0])

    #     self.assertFalse(shipper.can_short_circuit(autograding_config))

    # def test_cannot_short_circuit_many_testcases(self):
    #     """ We cannot short circuit if there are multiple testcases. """
    #     with open(os.path.join(TEST_DATA_DIR, 'complete_config_cpp_cats.json')) as infile:
    #         autograding_config = json.load(infile)
    #     self.assertFalse(shipper.can_short_circuit(autograding_config))


    """
    Unit testing individual functions using mocks.
    """

    @pytest.fixture(autouse=True)
    def _pass_fixtures(self, capsys):
        self.capsys = capsys

    def test_checkout_vcs_repo(self):
        """ Check if they system can checkout a VCS repository under different configs """
        homework_paths = {}

        # build test assignment folders
        with open(os.path.join(TEST_DATA_SRC_DIR, 'shipper_config.json'), 'r') as infile:
            obj = json.load(infile)

        partial_path = os.path.join(obj["gradeable"], obj["who"], str(obj["version"]))
        course_dir = os.path.join(
            CONFIG.submitty['submitty_data_dir'],
            "courses",
            obj["semester"],
            obj["course"]
        )
        for folder in ["submissions", "checkout", "results"]:
            homework_paths[folder] = os.path.join(course_dir, folder, partial_path)
            os.makedirs(homework_paths[folder])
        
        for folder in ["config", "config/form"]:
            os.makedirs(os.path.join(course_dir, folder))

        course_config_file = os.path.join(course_dir, "config", "config.json")
        with open(course_config_file, 'w') as open_file:
            open_file.write("""
{
    "database_details": {
        "dbname": "submitty_fall22_cptr141"
    },
    "course_details": {
        "course_name": "Fundamentals of Programming I",
        "course_home_url": "https://class.wallawalla.edu/d2l/home/343700",
        "default_hw_late_days": 99,
        "default_student_late_days": 99,
        "zero_rubric_grades": false,
        "upload_message": "By submitting, you are confirming that you have read, understand, and agree to follow the Academic Integrity Policy.",
        "display_rainbow_grades_summary": false,
        "display_custom_message": false,
        "course_email": "Please contact your TA or instructor to submit a grade inquiry.",
        "vcs_base_url": "git@gitlab.cs.wallawalla.edu:{$user_id}/student141.git",
        "vcs_type": "git",
        "private_repository": "",
        "forum_enabled": false,
        "forum_create_thread_message": "",
        "regrade_enabled": false,
        "regrade_message": "Warning: Frivolous grade inquiries may lead to grade deductions or lost late days",
        "seating_only_for_instructor": false,
        "room_seating_gradeable_id": "",
        "auto_rainbow_grades": false,
        "queue_enabled": false,
        "queue_message": "",
        "queue_announcement_message": "",
        "polls_enabled": false,
        "polls_pts_for_correct": 1,
        "polls_pts_for_incorrect": 0,
        "seek_message_enabled": false,
        "seek_message_instructions": "Optionally, provide your local timezone, desired project topic, or other information that would be relevant to forming your team.",
        "git_autograding_branch": "main"
    }
}
""")

        course_form_config_file = os.path.join(course_dir, "config", "form", "form_homework_01.json")
        with open(course_form_config_file, 'w') as open_file:
            open_file.write("""
{
    "gradeable_id": "homework_01",
    "config_path": "/var/local/submitty/private_course_repositories/test_course/homework_01",
    "date_due": "2022-10-06 23:59:59-0700",
    "upload_type": "repository",
    "subdirectory": "/homework_01"
}
""")

        # Start test
        shipper.checkout_vcs_repo(CONFIG, os.path.join(TEST_DATA_SRC_DIR, 'shipper_config.json'))

        # Confirm standard out
        expected = "SHIPPER CHECKOUT VCS REPO  /home/peteca/Documents/work/CS Lab Admin/Submitty/autograder/tests/data/shipper_config.json\n"
        self.assertEqual(expected, self.capsys.readouterr().out)

        # Confirm VCS checkout logging message
        with open(os.path.join(homework_paths["results"], "logs/vcs_checkout.txt")) as open_file:
            expected_vcs_checkout = """VCS CHECKOUT
vcs_base_url git@gitlab.cs.wallawalla.edu:test_student/student141.git
vcs_subdirectory /hw01
vcs_path git@gitlab.cs.wallawalla.edu:test_student/student141.git
/usr/bin/git clone git@gitlab.cs.wallawalla.edu:test_student/student141.git /home/peteca/documents/test_suite/unitTests/autograder/test_environment/autograding/courses/test_term/test_course/checkout/hw01/test_student/11/tmp --depth 1 -b master

====================================

"""
            self.assertEqual(expected_vcs_checkout, open_file.read())
