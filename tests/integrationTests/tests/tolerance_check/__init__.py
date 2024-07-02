# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob
import shutil
import traceback

############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/tolerance_check/config"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/tolerance_check/submissions"

@prebuild
def initialize(test):
    try:
        os.mkdir(os.path.join(test.testcase_path, "assignment_config"))
    except OSError:
        pass
    try:
        data_path = os.path.join(test.testcase_path, "data")
        if os.path.isdir(data_path):
            shutil.rmtree(data_path)
        os.mkdir(data_path)
        os.mkdir(os.path.join(data_path, "test_output"))
    except OSError:
        pass
    try:
        os.mkdir(os.path.join(test.testcase_path, "data"))
    except OSError:
        pass
    subprocess.call(["cp",
                     os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "config.json"),
                     os.path.join(test.testcase_path, "assignment_config")])

    subprocess.call(["cp"] +
        glob.glob(os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_input", "test_input.txt")) +
        [os.path.join(test.testcase_path, "data")])

    subprocess.call(["cp"] +
        glob.glob(os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_output", "test_output.txt")) +
        [os.path.join(test.testcase_path, "data", "test_output")])

############################################################################

def cleanup(test):
    subprocess.call(["rm"] + ["-rf"] +
        glob.glob(os.path.join(test.testcase_path, "data", "*")))
    os.mkdir(os.path.join(test.testcase_path, "data", "test_output"))
    subprocess.call(["cp"] +
        glob.glob(os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_output", "*.txt")) +
        [os.path.join(test.testcase_path, "data", "test_output")])
    subprocess.call(["cp"] +
        glob.glob(os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_input", "test_input.txt")) +
        [os.path.join(test.testcase_path, "data")])


@testcase
def schema_validation(test):
    cleanup(test)
    config_path = os.path.join(test.testcase_path, 'assignment_config', 'complete_config.json')
    try:
        test.validate_complete_config(config_path)
    except Exception:
        traceback.print_exc()
        raise


@testcase
def solution(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "solution.py"),
                     os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    test.diff("test01/STDOUT.txt","data/test_output/test_output.txt")
    test.empty_file("test01/STDERR.txt")
    test.empty_json_diff("test01/0_diff.json")
    test.diff("grade.txt", "grade.txt_solution", "-b")
    test.json_diff("results.json", "results.json_solution")


@testcase
def tolerance(test):
    pass
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "tolerance.py"),
                     os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    test.diff("test01/STDOUT.txt","STDOUT_tolerance.txt")
    test.diff("grade.txt", "grade.txt_tolerance", "-b")
    test.json_diff("results.json", "results.json_tolerance")


@testcase
def buggy(test):
    pass
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "buggy.py"),
                     os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    test.diff("test01/STDOUT.txt","STDOUT_buggy.txt")
    test.diff("grade.txt", "grade.txt_buggy", "-b")
    test.json_diff("results.json", "results.json_buggy")

@testcase
def format_flaw(test):
    pass
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "format_flaw.py"),
                     os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    test.diff("test01/STDOUT.txt","STDOUT_format_flaw.txt")
    test.diff("grade.txt", "grade.txt_format_flaw", "-b")
    test.json_diff("results.json", "results.json_format_flaw")

@testcase
def extra_spaces(test):
    pass
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "extra_spaces.py"),
                     os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    test.diff("test01/STDOUT.txt","STDOUT_extra_spaces.txt")
    test.diff("grade.txt", "grade.txt_extra_spaces", "-b")
    test.json_diff("results.json", "results.json_extra_spaces")

@testcase
def fewer_spaces(test):
    pass
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "fewer_spaces.py"),
                     os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    test.diff("test01/STDOUT.txt","STDOUT_fewer_spaces.txt")
    test.diff("grade.txt", "grade.txt_fewer_spaces", "-b")
    test.json_diff("results.json", "results.json_fewer_spaces")
