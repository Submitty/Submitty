# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, HSS_INSTALL_DIR

import subprocess
import os
import glob


############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = HSS_INSTALL_DIR + "/sample_files/sample_assignment_config/python_buggy_output"
SAMPLE_SUBMISSIONS       = HSS_INSTALL_DIR + "/sample_files/sample_submissions/python_buggy_output"

@prebuild
def initialize(test):
    try:
        os.mkdir(os.path.join(test.testcase_path, "assignment_config"))
    except OSError:
        pass
    try:
        os.mkdir(os.path.join(test.testcase_path, "data"))
    except OSError:
        pass
    try:
        os.mkdir(os.path.join(test.testcase_path, "data/part1"))
    except OSError:
        pass

    subprocess.call(["cp",
        os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "config.h"),
        os.path.join(test.testcase_path, "assignment_config")])
    subprocess.call(["cp",
        os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_input", "gettysburg_address.txt"),
        os.path.join(test.testcase_path, "data")])
    subprocess.call(["cp",
        os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_output", "output_instructor.txt"),
        os.path.join(test.testcase_path, "data")])

    subprocess.call(["cp"] +
            glob.glob(os.path.join(SAMPLE_SUBMISSIONS, "*.py")) +
            [os.path.join(test.testcase_path, "data/part1")])


############################################################################


@testcase
def check_output(test):
    test.run_run()
    test.diff("test01_output_correct.txt","data/output_instructor.txt")
    test.diff("test02_output_duplicates.txt","duplicate_lines.txt")
    test.diff("test03_output_duplicates.txt","duplicate_lines.txt")
    test.diff("test04_output_extra.txt","extra_lines.txt")
    test.diff("test05_output_extra.txt","extra_lines.txt")
    test.diff("test06_output_missing.txt","missing_lines.txt")
    test.diff("test07_output_missing.txt","missing_lines.txt")
    test.diff("test08_output_reordered.txt","output_reordered.txt")
    test.diff("test09_output_reordered.txt","output_reordered.txt")

@testcase
def check_json(test):
    test.run_validator()
    test.json_diff("test01_0_diff.json")
    test.json_diff("test02_0_diff.json")
    test.json_diff("test03_0_diff.json")
    test.json_diff("test04_0_diff.json")
    test.json_diff("test05_0_diff.json")
    test.json_diff("test06_0_diff.json")
    test.json_diff("test07_0_diff.json")
    test.json_diff("test08_0_diff.json")
    test.json_diff("test09_0_diff.json")

@testcase
def check_grade(test):
    test.diff("submission.json")

@testcase
def check_empty(test):
    test.empty_file("test01_STDERR.txt")
    test.empty_file("test01_STDOUT.txt")
    test.empty_file("test02_STDERR.txt")
    test.empty_file("test02_STDOUT.txt")
    test.empty_file("test03_STDERR.txt")
    test.empty_file("test03_STDOUT.txt")
    test.empty_file("test04_STDERR.txt")
    test.empty_file("test04_STDOUT.txt")
    test.empty_file("test05_STDERR.txt")
    test.empty_file("test05_STDOUT.txt")
    test.empty_file("test06_STDERR.txt")
    test.empty_file("test06_STDOUT.txt")
    test.empty_file("test07_STDERR.txt")
    test.empty_file("test07_STDOUT.txt")
    test.empty_file("test08_STDERR.txt")
    test.empty_file("test08_STDOUT.txt")
    test.empty_file("test09_STDERR.txt")
    test.empty_file("test09_STDOUT.txt")

    test.empty_file("test01_execute_logfile.txt")
    test.empty_file("test02_execute_logfile.txt")
    test.empty_file("test03_execute_logfile.txt")
    test.empty_file("test04_execute_logfile.txt")
    test.empty_file("test05_execute_logfile.txt")
    test.empty_file("test06_execute_logfile.txt")
    test.empty_file("test07_execute_logfile.txt")
    test.empty_file("test08_execute_logfile.txt")
    test.empty_file("test09_execute_logfile.txt")
