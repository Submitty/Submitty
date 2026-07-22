# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob
import shutil
import traceback

############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/multifile_config_python_greeting/config"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/multifile_config_python_greeting/submissions"

@prebuild
def initialize(test):
    print ("TEST INITIALIZE")
    try:
        os.mkdir(os.path.join(test.testcase_path, "assignment_config"))
    except OSError:
        pass

    try:
        os.mkdir(os.path.join(test.testcase_path, "assignment_config", "includes"))
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

    subprocess.call(["cp",
        os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "config.json"),
        os.path.join(test.testcase_path, "assignment_config")])

    subprocess.call(["cp"] +
        glob.glob(os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "includes", "*.txt")) +
        [os.path.join(test.testcase_path, "assignment_config", "includes")])
    
    subprocess.call(["cp"] +
        glob.glob(os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_input", "*.txt")) +
        [os.path.join(test.testcase_path, "data")])

    subprocess.call(["cp"] +
        glob.glob(os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_output", "*.txt")) +
        [os.path.join(test.testcase_path, "data", "test_output")])


############################################################################

def cleanup(test):
    print ("TEST CLEANUP")

    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "*py")))

    subprocess.call(["rm"] + ["-f"] +
        glob.glob(os.path.join(test.testcase_path, "data", "result*")))
    subprocess.call(["rm"] + ["-rf"] +
        glob.glob(os.path.join(test.testcase_path, "data", "test*")))
    subprocess.call(["rm"] + ["-f"] +
        glob.glob(os.path.join(test.testcase_path, "data", "*out")))

    os.mkdir(os.path.join(test.testcase_path, "data", "test_output"))
    subprocess.call(["cp"] +
        glob.glob(os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_input", "*.txt")) +
        [os.path.join(test.testcase_path, "data")])
    subprocess.call(["cp"] +
        glob.glob(os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_output", "*.txt")) +
        [os.path.join(test.testcase_path, "data", "test_output")])

 
@testcase
def schema_validation(test):
    print ("TEST SCHEMA VALIDATION")
    cleanup(test)
    config_path = os.path.join(test.testcase_path, 'assignment_config', 'complete_config.json')
    try:
        test.validate_complete_config(config_path)
    except Exception:
        traceback.print_exc()
        raise

@testcase
def correct(test):
    print ("TEST CORRECT")
    cleanup(test)
    subprocess.call(["cp",
        os.path.join(SAMPLE_SUBMISSIONS, "correct.py"),
        os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","grade.txt_correct","-b")
    test.json_diff("results.json","results.json_correct")

@testcase
def hardcoded(test):
    print ("TEST HARDCODED")
    cleanup(test)
    subprocess.call(["cp",
        os.path.join(SAMPLE_SUBMISSIONS, "hardcoded.py"),
        os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","grade.txt_hardcoded","-b")
    test.json_diff("results.json","results.json_hardcoded")

@testcase
def missingexclamation(test):
    print ("TEST MISSINGEXCLAMATION")
    cleanup(test)
    subprocess.call(["cp",
        os.path.join(SAMPLE_SUBMISSIONS, "missing_exclamation.py"),
        os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","grade.txt_missing_exclamation","-b")
    test.json_diff("results.json","results.json_missing_exclamation")


