# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob
import shutil
import traceback

############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/qiskit_circuit_draw_diff/config"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/qiskit_circuit_draw_diff/submissions"

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
def correct(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "correct.py"),
                     os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    test.diff("test01/STDOUT.txt","data/test_output/test_output.txt")
    test.empty_file("test01/STDERR.txt")
    test.empty_json_diff("test01/0_diff.json")
    test.diff("grade.txt", "grade.txt_correct", "-b")
    test.json_diff("results.json", "results.json_correct")


@testcase
def incorrect(test):
    pass
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "incorrect.py"),
                     os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    test.diff("test01/STDOUT.txt","STDOUT_incorrect.txt")
    test.diff("grade.txt", "grade.txt_incorrect", "-b")
    test.json_diff("results.json", "results.json_incorrect")


@testcase
def partially_correct(test):
    pass
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "partially_correct.py"),
                     os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    test.diff("test01/STDOUT.txt","STDOUT_partially_correct.txt")
    test.diff("grade.txt", "grade.txt_partially_correct", "-b")
    test.json_diff("results.json", "results.json_partially_correct")
