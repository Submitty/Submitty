import os
import json
import shutil
import tempfile
import unittest
import contextlib
import copy
import subprocess
from unittest.mock import MagicMock, patch

import submitty_autograding_shipper as shipper
from autograder import config

SCRIPT_DIR = os.path.dirname(os.path.realpath(__file__))
# Path any auxiliary test files (e.g. autograding configs)
TEST_DATA_SRC_DIR = os.path.join(SCRIPT_DIR, "data")

# Where to dump intermediate test files
# The GIT_CHECKOUT folder is guaranteed to be within the Submitty install dir.
WORKING_DIR = os.path.abspath(
    os.path.join("..", "..", "..", "test_suite", "unitTests", "autograder")
)
# Path to place our temporary data files
TEST_DATA_DIR = os.path.join(WORKING_DIR, "data")
# Equivalent to install dir
TEST_ENVIRONMENT = os.path.join(WORKING_DIR, "test_environment")

# Holds all system config files (equivalent to /usr/local/submitty/config)
CONFIG_DIR = os.path.join(TEST_ENVIRONMENT, "config")
SUBMITTY_DATA_DIR = os.path.join(TEST_ENVIRONMENT, "autograding")

# Autograding directories
TODO_DIR = os.path.join(SUBMITTY_DATA_DIR, "autograding_TODO")
DONE_DIR = os.path.join(SUBMITTY_DATA_DIR, "autograding_DONE")
TO_BE_GRADED = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_queue")
GRADING = os.path.join(SUBMITTY_DATA_DIR, "in_progress_grading")

# Log directorories
LOG_PATH = os.path.join(TEST_ENVIRONMENT, "logs")
STACK_TRACES = os.path.join(LOG_PATH, "autograding_stack_traces")
AUTOGRADING_LOGS = os.path.join(LOG_PATH, "autograding")
# The autograder.Config object to pass to the shipper.
CONFIG = None


def get_paths():
    homework_paths = {}

    CONFIG = config.Config.path_constructor(CONFIG_DIR, "TEST")

    # Build the homework/checkout path folders
    with open(os.path.join(TEST_DATA_DIR, "shipper_config.json"), "r") as infile:
        obj = json.load(infile)

    partial_path = os.path.join(obj["gradeable"], obj["who"], str(obj["version"]))
    course_dir = os.path.join(
        CONFIG.submitty["submitty_data_dir"], "courses", obj["semester"], obj["course"]
    )
    for folder in ["submissions", "checkout", "results"]:
        homework_paths[folder] = os.path.join(course_dir, folder, partial_path)

    checkout_path = os.path.join(course_dir, "checkout", partial_path)
    return {
        "checkout": checkout_path,
        "course": course_dir,
        "partial": partial_path,
        "homework": homework_paths,
    }


class TestAutogradingShipper(unittest.TestCase):
    """Unittest TestCase."""

    @classmethod
    def tearDownClass(cls):
        """Tear down the mock environment for these testcases."""
        # Remove the test environment.
        with contextlib.suppress(FileNotFoundError):
            shutil.rmtree(WORKING_DIR)

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
            "submitty_data_dir": SUBMITTY_DATA_DIR,
            "submitty_install_dir": TEST_ENVIRONMENT,
            "autograding_log_path": AUTOGRADING_LOGS,
            "site_log_path": LOG_PATH,
            "submission_url": "/fake/url/for/submission/",
            "vcs_url": "/fake/url/for/vcs/submission/",
        }
        users_json = {
            # Pretend that we are the daemon user.
            "daemon_uid": os.getuid()
        }
        # The database json is required by autograder/insert_database_version_data.py
        # When we test that script, a mock database may be needed, and these
        # values will have to be updated.
        database_json = {
            "database_user": "foo",
            "database_host": "bar",
            "database_password": "password",
        }

        for filename, json_file in [
            ("submitty", submitty_json),
            ("submitty_users", users_json),
            ("database", database_json),
        ]:
            with open(os.path.join(CONFIG_DIR, f"{filename}.json"), "w") as outfile:
                json.dump(json_file, outfile, indent=4)

        # Instantiate the shipper's config object
        CONFIG = config.Config.path_constructor(CONFIG_DIR, "TEST")
        shipper.instantiate_global_variables(CONFIG)

        # Create git submission paths
        homework_paths = {}
        paths = get_paths()

        for folder in ["submissions", "checkout", "results"]:
            homework_paths[folder] = os.path.join(paths["course"], folder, paths["partial"])
            os.makedirs(homework_paths[folder])

        for folder in ["config", "config/form"]:
            os.makedirs(os.path.join(paths["course"], folder))

        course_config_file = os.path.join(paths["course"], "config", "config.json")
        # Open config file and copy to test directory
        with open(os.path.join(TEST_DATA_DIR, "config_files", "config.json")) as config_file:
            with open(course_config_file, "w") as new_config_file:
                new_config_file.write(config_file.read().replace("VCS_BASE_URL", TEST_DATA_DIR))

        # Write course form config
        course_form_config_file = os.path.join(
            paths["course"], "config", "form", "form_homework_01.json"
        )
        with open(course_form_config_file, "w") as open_file:
            with open(
                os.path.join(TEST_DATA_DIR, "config_files", "homework_form.json")
            ) as form_file:
                open_file.write(form_file.read().replace("CONFIG_PATH", TEST_DATA_DIR))

        # Initialize git homework directory
        create_git_repository = """
        cd {}/homework_01;
        git init --initial-branch "master";
        git config user.email "test@email.com";
        git config user.name "Test Shipper";
        git add -A;
        git commit -m "testing"
        """
        os.system(create_git_repository.format(TEST_DATA_DIR))

    def test_can_short_no_testcases(self):
        """We should be able to short circuit configs with no testcases"""
        autograding_config = {"testcases": []}
        self.assertTrue(shipper.can_short_circuit(autograding_config))

    def test_can_short_circuit_max_submission(self):
        """We should be able to short circuit if the only testcase is max_submission"""
        with open(os.path.join(TEST_DATA_DIR, "complete_config_upload_only.json")) as infile:
            autograding_config = json.load(infile)
        self.assertTrue(shipper.can_short_circuit(autograding_config))

    def test_cannot_short_circuit_single_non_file_submission_testcase(self):
        """
        If there is only one testcase, but it is non-file submission, we cannot short circuit.
        """
        with open(os.path.join(TEST_DATA_DIR, "complete_config_cpp_cats.json")) as infile:
            tmp_config = json.load(infile)
        # Create a config that is a copy of cpp cats but with only one testcase.
        autograding_config = copy.deepcopy(tmp_config)
        autograding_config["testcases"] = []
        autograding_config["testcases"].append(tmp_config["testcases"][0])

        self.assertFalse(shipper.can_short_circuit(autograding_config))

    def test_cannot_short_circuit_many_testcases(self):
        """We cannot short circuit if there are multiple testcases."""
        with open(os.path.join(TEST_DATA_DIR, "complete_config_cpp_cats.json")) as infile:
            autograding_config = json.load(infile)
        self.assertFalse(shipper.can_short_circuit(autograding_config))

    """
    Unit testing individual functions using mocks.
    """

    def test_correct_checkout(self):
        """
        This function tests the correct output of the checkout_vcs_repo function.
        To start, the submission directory is setup, and the path variables are retrieved.
        After this, the shipper is run, and the checkout path is scanned to make sure
        that there is no 'failed' test files.
        If that passes, the expected vcs checkout log is compared to the actual log.
        """
        paths = get_paths()

        # Start test
        shipper.checkout_vcs_repo(CONFIG, os.path.join(TEST_DATA_DIR, "shipper_config.json"))

        # Make sure none of the tests have failed before checking valid output
        failed_files = [file for file in os.listdir(paths["checkout"]) if file.startswith("failed")]
        self.assertTrue(len(failed_files) == 0)

        # Confirm VCS checkout logging messages
        actual_logs_path = os.path.join(paths["homework"]["results"], "logs/vcs_checkout.txt")
        with open(actual_logs_path, "r") as actual_vcs_checkout:
            actual_output = actual_vcs_checkout.read()

            # Check if the paths related to the vcs are correct
            expected_logs_path = os.path.join(
                TEST_DATA_DIR, "config_files", "expected_vcs_checkout.txt"
            )
            with open(expected_logs_path, "r") as expected_vcs_checkout:
                # Check if the expected checkout with local path variables is in the actual output
                new_expected = expected_vcs_checkout.read().replace("TEST_DATA_PATH", TEST_DATA_DIR)
                self.assertTrue(
                    new_expected.replace("HOMEWORK_PATH", TEST_DATA_DIR + "/homework_01").replace(
                        "CHECKOUT_PATH", paths["checkout"]
                    )
                    in actual_output,
                    "Incorrect File Locations, {}",
                )

            # Confirm the subdirectory is cloned and is found at the correct path
            expected_subdirectory = "{CHECKOUT_PATH}/subdirectory:".format(
                CHECKOUT_PATH=paths["checkout"]
            )
            self.assertTrue(
                expected_subdirectory in actual_output,
                "subdirectory not cloned/incorrect location",
            )

    def test_failed_to_clone(self):
        """
        This test is to verify the output when the shipper fails clone the repository
        """
        paths = get_paths()
        shipper.checkout_vcs_repo(CONFIG, os.path.join(TEST_DATA_DIR, "shipper_config.json"))
        failed_file = paths["checkout"] + "/failed_to_clone_repository.txt"
        self.assertTrue(os.path.isfile(failed_file), "Failed to cause a clone repository failure")

    def test_invalid_subdirectory_files(self):
        """
        This test is to verify the output when the shipper fails
        to clone a repository with an empty/invalid subdirectory.
        """
        paths = get_paths()
        os.chdir(TEST_DATA_DIR)
        config_file_path = os.path.join(paths["course"], "config", "form", "form_homework_01.json")
        base_file_path = os.path.join(
            TEST_DATA_DIR, "config_files", "homework_form_subdirectory.json"
        )
        with open(config_file_path, "w+") as form_config_file:
            with open(base_file_path, "r") as base_config_file:
                form_config_file.write(base_config_file.read().replace("homework_02", "bad_path"))

        # Setup the new git repository in the test folder.
        shutil.rmtree(os.path.join(TEST_DATA_DIR, "homework_01/subdirectory/homework_02"))
        shipper.checkout_vcs_repo(CONFIG, os.path.join(TEST_DATA_DIR, "shipper_config.json"))
        failed_file = paths["checkout"] + "/failed_subdirectory_invalid_or_empty.txt"
        self.assertTrue(os.path.isfile(failed_file), "Failed test with no files in subdirectory")

    def test_good_subdirectory_files(self):
        """
        This test is to verify the output when the shipper
        successfully tries to clone a repository with the homework in
        a subdirectory.
        """
        paths = get_paths()
        os.chdir(TEST_DATA_DIR)
        config_file_path = os.path.join(paths["course"], "config", "form", "form_homework_01.json")
        base_file_path = os.path.join(
            TEST_DATA_DIR, "config_files", "homework_form_subdirectory.json"
        )
        with open(config_file_path, "w+") as form_config_file:
            with open(base_file_path, "r") as base_config_file:
                form_config_file.write(base_config_file.read())

        shipper.checkout_vcs_repo(CONFIG, os.path.join(TEST_DATA_DIR, "shipper_config.json"))
        failed_files = [file for file in os.listdir(paths["checkout"]) if file.startswith("failed")]
        self.assertTrue(len(failed_files) == 0)

    def create_outside_directory(self) -> str:
        """Create a directory outside of any repository, holding files that must stay private."""
        outside = os.path.join(WORKING_DIR, "outside_repository")
        os.makedirs(os.path.join(outside, "nested"), exist_ok=True)
        for path in ["secret.txt", "nested/secret.txt"]:
            with open(os.path.join(outside, path), "w") as outfile:
                outfile.write("private")
        return outside

    def checkout_repository(self, files: dict, links: dict, subdirectory: str = "") -> dict:
        """
        Commit the given files and symlinks to a new repository, run the shipper's
        checkout on it, and return the resulting job object.
        """
        # The checkout leaves the process in a directory that it has deleted.
        self.addCleanup(os.chdir, SCRIPT_DIR)
        os.chdir(TEST_DATA_DIR)

        repository = "symlink_homework"
        repository_path = os.path.join(TEST_DATA_DIR, repository)
        shutil.rmtree(repository_path, ignore_errors=True)
        os.makedirs(repository_path)
        for path, contents in files.items():
            full_path = os.path.join(repository_path, path)
            os.makedirs(os.path.dirname(full_path), exist_ok=True)
            with open(full_path, "w") as outfile:
                outfile.write(contents)
        for path, target in links.items():
            full_path = os.path.join(repository_path, path)
            os.makedirs(os.path.dirname(full_path), exist_ok=True)
            os.symlink(target, full_path)

        git = ["git", "-c", "user.email=test@email.com", "-c", "user.name=Test Shipper"]
        for command in [
            ["init", "--initial-branch", "master"],
            ["add", "-A"],
            ["commit", "-m", "testing"],
        ]:
            subprocess.run(git + command, cwd=repository_path, check=True)

        with open(os.path.join(TEST_DATA_DIR, "config_files", "homework_form.json")) as form_file:
            form = json.load(form_file)
        form["subdirectory"] = subdirectory
        form["vcs_partial_path"] = repository
        course_path = get_paths()["course"]
        with open(os.path.join(course_path, "config", "form", "form_homework_01.json"), "w") as f:
            json.dump(form, f)

        return shipper.checkout_vcs_repo(CONFIG, os.path.join(TEST_DATA_DIR, "shipper_config.json"))

    def checkout_entries(self) -> list:
        """List everything in the checkout directory, relative to that directory."""
        checkout_path = get_paths()["checkout"]
        entries = []
        for root, directories, files in os.walk(checkout_path):
            for name in directories + files:
                entries.append(os.path.relpath(os.path.join(root, name), checkout_path))
        return sorted(entries)

    def test_symlinks_are_removed_without_copying_targets(self):
        """Every kind of symlink is dropped from a checkout, and no target is copied into it."""
        outside = self.create_outside_directory()
        job = self.checkout_repository(
            files={"kept.txt": "kept", "src/nested.txt": "nested"},
            links={
                "file_link": os.path.join(outside, "secret.txt"),
                "directory_link": outside,
                "src/nested_link": os.path.join(outside, "secret.txt"),
                "dangling_link": os.path.join(outside, "missing.txt"),
                "inside_link": "kept.txt",
            },
        )

        self.assertEqual(self.checkout_entries(), ["kept.txt", "src", "src/nested.txt"])
        self.assertTrue(job["checkout_included_symlinks"])
        self.assertEqual(job["checkout_total_size"], len("kept") + len("nested"))

    def test_symlinked_subdirectory_is_not_copied(self):
        """A symlink at or above the gradeable's subdirectory cannot redirect the checkout."""
        outside = self.create_outside_directory()
        for subdirectory in ["homework", "homework/nested", "/homework"]:
            with self.subTest(subdirectory=subdirectory):
                job = self.checkout_repository(
                    files={"kept.txt": "kept"},
                    links={"homework": outside},
                    subdirectory=subdirectory,
                )

                self.assertEqual(
                    self.checkout_entries(), ["failed_subdirectory_invalid_or_empty.txt"]
                )
                self.assertTrue(job["checkout_included_symlinks"])

    def test_symlink_free_repository_is_not_flagged(self):
        """A repository without symlinks is checked out in full and is not flagged."""
        job = self.checkout_repository(files={"kept.txt": "kept"}, links={})

        self.assertEqual(self.checkout_entries(), ["kept.txt"])
        self.assertFalse(job["checkout_included_symlinks"])
        self.assertEqual(job["checkout_total_size"], len("kept"))

    def test_symlink_cleanup_failure_discards_clone(self):
        """
        A failed symlink cleanup discards the temporary clone. Leaving it behind would
        make the final cleanup fail too, and the shipper would retry the job forever.
        """
        outside = self.create_outside_directory()
        real_cleanup = shipper.calculate_size_cleanup_symlinks

        def fail_on_temporary_clone(directory):
            if directory.endswith("/tmp"):
                raise OSError("File name too long")
            return real_cleanup(directory)

        with patch.object(shipper, "calculate_size_cleanup_symlinks", fail_on_temporary_clone):
            self.checkout_repository(
                files={"kept.txt": "kept"},
                links={"file_link": os.path.join(outside, "secret.txt")},
            )

        self.assertEqual(self.checkout_entries(), ["failed_subdirectory_invalid_or_empty.txt"])


class TestLoadAutogradingWorkersJson(unittest.TestCase):
    """Tests for load_autograding_workers_json schema validation."""

    VALID_WORKERS = {
        "primary": {
            "address": "localhost",
            "username": "",
            "num_autograding_workers": 5,
            "enabled": True,
            "capabilities": ["default"],
        }
    }

    def setUp(self):
        self.temp_dir = tempfile.mkdtemp()
        config_dir = os.path.join(self.temp_dir, "config")
        os.makedirs(config_dir)
        self.workers_json_path = os.path.join(config_dir, "autograding_workers.json")

        self.mock_config = MagicMock()
        self.mock_config.submitty = {"submitty_install_dir": self.temp_dir}

    def tearDown(self):
        shutil.rmtree(self.temp_dir)

    def _write_workers(self, workers):
        with open(self.workers_json_path, "w") as f:
            json.dump(workers, f)

    def test_valid_config_loads_successfully(self):
        """A correctly formed autograding_workers.json should load without error."""
        self._write_workers(self.VALID_WORKERS)
        result = shipper.load_autograding_workers_json(self.mock_config)
        self.assertEqual(result, self.VALID_WORKERS)

    def test_missing_num_autograding_workers_raises(self):
        """Missing num_autograding_workers should raise SystemExit with a clear message."""
        workers = copy.deepcopy(self.VALID_WORKERS)
        del workers["primary"]["num_autograding_workers"]
        self._write_workers(workers)
        with self.assertRaises(SystemExit) as ctx:
            shipper.load_autograding_workers_json(self.mock_config)
        self.assertIn("num_autograding_workers", str(ctx.exception))

    def test_missing_enabled_raises(self):
        """Missing enabled field should raise SystemExit with a clear message."""
        workers = copy.deepcopy(self.VALID_WORKERS)
        del workers["primary"]["enabled"]
        self._write_workers(workers)
        with self.assertRaises(SystemExit) as ctx:
            shipper.load_autograding_workers_json(self.mock_config)
        self.assertIn("enabled", str(ctx.exception))

    def test_wrong_type_for_num_workers_raises(self):
        """num_autograding_workers as a string should raise SystemExit."""
        workers = copy.deepcopy(self.VALID_WORKERS)
        workers["primary"]["num_autograding_workers"] = "five"
        self._write_workers(workers)
        with self.assertRaises(SystemExit) as ctx:
            shipper.load_autograding_workers_json(self.mock_config)
        self.assertIn("num_autograding_workers", str(ctx.exception))

    def test_num_workers_below_minimum_raises(self):
        """num_autograding_workers of 0 should raise SystemExit."""
        workers = copy.deepcopy(self.VALID_WORKERS)
        workers["primary"]["num_autograding_workers"] = 0
        self._write_workers(workers)
        with self.assertRaises(SystemExit):
            shipper.load_autograding_workers_json(self.mock_config)

    def test_empty_capabilities_raises(self):
        """An empty capabilities list should raise SystemExit."""
        workers = copy.deepcopy(self.VALID_WORKERS)
        workers["primary"]["capabilities"] = []
        self._write_workers(workers)
        with self.assertRaises(SystemExit):
            shipper.load_autograding_workers_json(self.mock_config)

    def test_unknown_key_raises(self):
        """An unrecognised key (e.g. a typo) should raise SystemExit."""
        workers = copy.deepcopy(self.VALID_WORKERS)
        workers["primary"]["capabilites"] = ["default"]  # common typo
        self._write_workers(workers)
        with self.assertRaises(SystemExit) as ctx:
            shipper.load_autograding_workers_json(self.mock_config)
        self.assertIn("capabilites", str(ctx.exception))

    def test_multiple_workers_valid(self):
        """Multiple valid worker entries should load successfully."""
        workers = copy.deepcopy(self.VALID_WORKERS)
        workers["worker2"] = {
            "address": "192.168.1.50",
            "username": "submitty",
            "num_autograding_workers": 10,
            "enabled": False,
            "capabilities": ["default", "python"],
        }
        self._write_workers(workers)
        result = shipper.load_autograding_workers_json(self.mock_config)
        self.assertEqual(result, workers)
