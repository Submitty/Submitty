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

    def test_checkout_vcs_repo(self):
        test_data_path = TEST_DATA_DIR
      #  test_data_source_path = TEST_DATA_SRC_DIR
        """ Check if they system can checkout a VCS repository under different configs """
        homework_paths = {}

        # build test assignment folders
        with open(os.path.join(test_data_path, 'shipper_config.json'), 'r') as infile:
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
        #open config file and copy to test directory
        with open(os.path.join(test_data_path, "config_files", 'config.json')) as config_file:
            
            with open(course_config_file, 'w') as new_config_file:
                open_file.write(config_file.read().replace("VCS_BASE_URL", test_data_path))
        
        # write course form config
        course_form_config_file = os.path.join(course_dir, "config", "form", "form_homework_01.json")
        with open(course_form_config_file, 'w') as open_file:
            with open(os.path.join(test_data_path, "config_files", 'homework_form.json')) as form_file:
                open_file.write(form_file.read().replace("CONFIG_PATH", test_data_path))

        # Initialize git homework directory
        os.system("cd {}/homework_01; git init; git add -A; git commit -m \"testing\"".format(test_data_path))
        # Start test
        results = shipper.checkout_vcs_repo(CONFIG, os.path.join(test_data_path, 'shipper_config.json'))

        #get the path of the folder to clone the repository to, to then checkout later
        checkout_path = os.path.join(course_dir, "checkout", partial_path)
      
        # Confirm standard out
        expected = "SHIPPER CHECKOUT VCS REPO  {}\n".format(test_data_path + "/shipper_config.json")
        self.assertEqual(expected, self.capsys.readouterr().out)

        # Check if the repository has failed to clone
        failed_files = [file for file in os.listdir(checkout_path) if file.startswith("failed")]
        self.assertTrue(len(failed_files) == 0)
     
        # Confirm VCS checkout logging messages
        with open(os.path.join(homework_paths["results"], "logs/vcs_checkout.txt")) as actual_vcs_checkout:
           check_against = actual_vcs_checkout.read()
           # Check if the paths related to the vcs  are correct
            with open(os.path.join(test_data_path, "config_files", 'homework_form.json')) as expected_vcs_checkout:
                self.assertTrue(expected_vcs_checkout.replace("TEST_DATA_PATH", test_data_path, "HOMEWORK_PATH", test_data_path + "/homework_01", "CHECKOUT_PATH", checkout_path) in check_against, "Incorrect File Locations") 

            #confirm the folder cloned and is found at the correct path
            expected_folder_contains = "{CHECKOUT_PATH}:\ntotal 1".format(CHECKOUT_PATH = checkout_path)
            self.assertTrue(expected_folder_contains in check_against, "Folder not cloned/incorrect location")

            #confirm the subfolder is cloned and is found at the correct path
            expected_subfolder = "{CHECKOUT_PATH}/subfolder:\ntotal 1".format(CHECKOUT_PATH = checkout_path)
            self.assertTrue(expected_subfolder in check_against, "Subfolder not cloned/incorrect location")

            #Confirm the size of the file "block" (make sure there aren't any extra/unwanted files)
            expected_size = "23K	{CHECKOUT_PATH}".format(CHECKOUT_PATH = checkout_path)
            self.assertTrue(expected_size in check_against, "File size is incorrect")

      # Check if the repository has failed to clone (in this case, the directory is not a repository)
      def test_failed_to_clone_no_repo(self):
        os.system('rm {}/homework_01/.git'.format(TEST_DATA_DIR))
        shipper.checkout_vcs_repo(CONFIG, os.path.join(TEST_DATA_DIR, 'shipper_config.json'))
        self.assertTrue(os.path.isfile(checkout_path+"/failed_to_clone_repository.txt"), "Failed to clone repository")
      
      # Check if the repository has a current version on the master branch
      # TODO : make this test work without having the repository fail to clone. 
      # Investigate whether configuring a different path works, or if you have to not commit, etc. 
      def test_correct_version(self):
        os.system('cd {}/homework_01; git init'.format(TEST_DATA_DIR))
        shipper.checkout_vcs_repo(CONFIG, os.path.join(TEST_DATA_DIR, 'shipper_config.json'))
        self.assertTrue(os.path.isfile(checkout_path+"/failed_to_determine_version_on_specifed_branch.txt"), "Failed to determine branch")
        
      def test_invalid_url(self):
        # # Check if the repository url is not valid
        with open(os.path.join(TEST_DATA_DIR, 'shipper_config.json'), 'w') as config_file:
          valid_config = open_file.read()
          open_file.write(valid_config.replace("test_student", ""))
          shipper.checkout_vcs_repo(CONFIG, os.path.join(TEST_DATA_DIR, 'shipper_config.json'))
          open_file.write(valid_config)
        self.assertTrue(os.path.isfile(checkout_path+"/failed_to_construct_valid_repository_url.txt"), "Failed to create a valid repository url")
     