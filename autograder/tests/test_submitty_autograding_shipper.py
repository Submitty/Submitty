import os
import json
import shutil
import sys
import unittest
import contextlib
import copy

import autograder
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
        global WORKING_DIR
        # Remove the test environment.
        with contextlib.suppress(FileNotFoundError):
            shutil.rmtree(WORKING_DIR)

    @classmethod
    def setUpClass(cls):
        """
        Sets up a mock environment roughly equivalent to the production server.
        As more features are needed, they should be added here
        """
        global TEST_ENVIRONMENT, CONFIG_DIR, SUBMITTY_DATA_DIR, TODO_DIR, DONE_DIR, TO_BE_GRADED,\
               GRADING, LOG_PATH, STACK_TRACES, AUTOGRADING_LOGS, CONFIG

        # Remove the test environment if it is left over from a previous run.
        with contextlib.suppress(FileNotFoundError):
            shutil.rmtree(TEST_ENVIRONMENT)

        # Make the working directory
        os.makedirs(WORKING_DIR, exist_ok=True)

        # Copy test data into the dir
        shutil.copytree(TEST_DATA_SRC_DIR, TEST_DATA_DIR)

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
            'submitty_data_dir' : SUBMITTY_DATA_DIR,
            'submitty_install_dir' : TEST_ENVIRONMENT,
            'autograding_log_path' : AUTOGRADING_LOGS,
            'site_log_path' : LOG_PATH,
            'submission_url' : '/fake/url/for/submission/',
            'vcs_url' : '/fake/url/for/vcs/submission/'
        }
        users_json = {
            # Pretend that we are the daemon user.
            'daemon_uid': os.getuid()
        }
        # The database json is required by autograder/insert_database_version_data.py
        # When we test that script, a mock database may be needed, and these
        # values will have to be updated.
        database_json = {
            'database_user' : 'foo',
            'database_host' : 'bar',
            'database_password' : 'password'
        }

        for filename, json_file in [
            ('submitty', submitty_json),
            ('submitty_users', users_json),
            ('database', database_json)
        ]:
            with open(os.path.join(CONFIG_DIR, f'{filename}.json'), 'w') as outfile:
                json.dump(json_file, outfile, indent=4)

        # Instantiate the shipper's config object
        CONFIG = config.Config.path_constructor(CONFIG_DIR)
        shipper.instantiate_global_variables(CONFIG)


    def test_can_short_no_testcases(self):
        """ We should be able to short circuit configs with no testcases  """
        autograding_config = {
            "testcases" : []
        }
        self.assertTrue(shipper.can_short_circuit(autograding_config))


    def test_can_short_circuit_max_submission(self):
        """ We should be able to short circuit if the only testcase is max_submission """
        global TEST_DATA_DIR

        with open(os.path.join(TEST_DATA_DIR, 'complete_config_upload_only.json')) as infile:
            autograding_config = json.load(infile)
        self.assertTrue(shipper.can_short_circuit(autograding_config))


    def test_cannot_short_circuit_single_non_file_submission_testcase(self):
        """
        If there is only one testcase, but it is non-file submission, we cannot short circuit.
        """
        global TEST_DATA_DIR
        with open(os.path.join(TEST_DATA_DIR, 'complete_config_cpp_cats.json')) as infile:
            tmp_config = json.load(infile)
        # Create a config that is a copy of cpp cats but with only one testcase.
        autograding_config = copy.deepcopy(tmp_config)
        autograding_config['testcases'] = []
        autograding_config['testcases'].append(tmp_config['testcases'][0])

        self.assertFalse(shipper.can_short_circuit(autograding_config))


    def test_cannot_short_circuit_many_testcases(self):
        """ We cannot short circuit if there are multiple testcases. """
        global TEST_DATA_DIR
        with open(os.path.join(TEST_DATA_DIR, 'complete_config_cpp_cats.json')) as infile:
            autograding_config = json.load(infile)
        self.assertFalse(shipper.can_short_circuit(autograding_config))
