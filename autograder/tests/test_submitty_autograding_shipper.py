import os
import json
import shutil
import unittest
import contextlib
import copy
import pytest
import difflib

import submitty_autograding_shipper as shipper
from autograder import config

SCRIPT_DIR = os.path.dirname(os.path.realpath(__file__))
# Path any auxiliary test files (e.g. autograding configs)
TEST_DATA_SRC_DIR = os.path.join(SCRIPT_DIR, 'data')

# Where to dump intermediate test files
# The GIT_CHECKOUT folder is guaranteed to be within the Submitty install dir.
WORKING_DIR = os.path.abspath(
    os.path.join('..', '..', '..', 'test_suite', 'unit_tests', 'autograder')
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

def get_paths():
    homework_paths = {}

    CONFIG = config.Config.path_constructor(CONFIG_DIR, 'TEST')
    #  test_data_source_path = TEST_DATA_SRC_DIR
    """ Check if they system can checkout a VCS repository under different configs """
       # build test assignment folders
    with open(os.path.join(TEST_DATA_DIR, 'shipper_config.json'), 'r') as infile:
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
    
    checkout_path = os.path.join(course_dir, "checkout", partial_path)
    return {"checkout" : checkout_path, "course" : course_dir, "partial" : partial_path, "homework" :homework_paths}

def setup_test_paths():
    homework_paths = {}
    paths = get_paths()
    
    for folder in ["submissions", "checkout", "results"]:
        homework_paths[folder] = os.path.join(paths["course"], folder, paths["partial"])
        os.makedirs(homework_paths[folder])

    for folder in ["config", "config/form"]:
        os.makedirs(os.path.join(paths["course"], folder))

    course_config_file = os.path.join(paths["course"], "config", "config.json")
    #open config file and copy to test directory
    with open(os.path.join(TEST_DATA_DIR, "config_files", 'config.json')) as config_file:
        with open(course_config_file, 'w') as new_config_file:
            new_config_file.write(config_file.read().replace("VCS_BASE_URL", TEST_DATA_DIR))
    
    # write course form config
    course_form_config_file = os.path.join(paths["course"], "config", "form", "form_homework_01.json")
    with open(course_form_config_file, 'w') as open_file:
        with open(os.path.join(TEST_DATA_DIR, "config_files", 'homework_form.json')) as form_file:
            open_file.write(form_file.read().replace("CONFIG_PATH", TEST_DATA_DIR))
    
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
        shutil.rmtree(TEST_DATA_DIR,  ignore_errors=True)
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
    
    def test_happy_path(self):
        setup_test_paths()
        paths = get_paths()
        # Initialize git homework directory
        os.system('cd {}/homework_01; git init; git add -A; git commit -m \"testing\"'.format(TEST_DATA_DIR))
        # Start test
        shipper.checkout_vcs_repo(CONFIG, os.path.join(TEST_DATA_DIR, 'shipper_config.json'))
        # Confirm standard out
        expected = "SHIPPER CHECKOUT VCS REPO  {}\n".format(TEST_DATA_DIR + "/shipper_config.json")
        self.assertEqual(expected, self.capsys.readouterr().out)
        # make sure none of the tests have failed before checking valid output
        failed_files = [file for file in os.listdir(paths["checkout"]) if file.startswith("failed")]
        self.assertTrue(len(failed_files) == 0)
     
        # Confirm VCS checkout logging messages
        with open(os.path.join(paths["homework"]["results"], "logs/vcs_checkout.txt"), 'r') as actual_vcs_checkout:
            correct_output = actual_vcs_checkout.read()
            # Check if the paths related to the vcs  are correct
            with open(os.path.join(TEST_DATA_DIR, "config_files", 'expected_vcs_checkout.txt'), 'r') as expected_vcs_checkout:
                self.assertTrue(expected_vcs_checkout.read().replace("TEST_DATA_PATH", TEST_DATA_DIR).replace("HOMEWORK_PATH", TEST_DATA_DIR + "/homework_01")
                .replace("CHECKOUT_PATH", paths["checkout"]) in correct_output, "Incorrect File Locations") 
        
            #confirm the subfolder is cloned and is found at the correct path
            expected_subfolder = "{CHECKOUT_PATH}/subfolder:\ntotal 1".format(CHECKOUT_PATH = paths["checkout"])
            self.assertTrue(expected_subfolder in correct_output, "Subfolder not cloned/incorrect location")

    def test_invalid_clone(self):
        paths = get_paths()
        os.system("rm {}/homework_01/.git".format(TEST_DATA_DIR))
        shipper.checkout_vcs_repo(CONFIG, os.path.join(TEST_DATA_DIR, 'shipper_config.json'))
        self.assertTrue(os.path.isfile(paths["checkout"]+"/failed_to_clone_repository.txt"), "Failed to cause a clone repository Failure")
   
    def test_valid_url(self):
        paths = get_paths()
        course_form_config_file = os.path.join(paths["course"], "config", "form", "form_homework_01.json")
        with open(course_form_config_file, 'w') as open_file:
            with open(os.path.join(TEST_DATA_DIR, "config_files", 'homework_form.json')) as form_file:
                open_file.write(form_file.read().replace("homework_01", ""))
        shipper.checkout_vcs_repo(CONFIG, os.path.join(TEST_DATA_DIR, 'shipper_config.json'))
        self.assertTrue(os.path.isfile(paths["checkout"]+"/failed_to_construct_valid_repository_url.txt"), "Failed to induce an invalid repository url")



## I don't know how to make this test fail without having the failed_to_clone part fail also. 

# According to the submitty_autograding_shipper comments at line 934, "if the repo is empty or the specified branch does not exist, this command will fail"
# however, the repository needs to have something in it in order to clone it, and it needs to have the correct branch in order to clone also, so both parts
# of this test will fail before even getting to this point.

        # os.system("rm {} -rf".format(checkout_path+"/"))
        # os.system('cd {}/homework_01; git init; git add -A; git commit -m \"testing\"; '.format(TEST_DATA_DIR))
        # shipper.checkout_vcs_repo(CONFIG, os.path.join(TEST_DATA_DIR, 'shipper_config.json'))
        # self.assertTrue(os.path.isfile(checkout_path+"/failed_to_determine_version_on_specifed_branch.txt"), "Failed to determine branch")