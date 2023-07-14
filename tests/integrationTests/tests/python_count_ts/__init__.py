# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import os
import glob
import shutil
import traceback

############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/python_count_ts/config"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/python_count_ts/submissions"

@prebuild
def initialize(test):
    config_path = os.path.join(test.testcase_path, "assignment_config")
    if (not os.path.exists(config_path)):
        os.mkdir(os.path.join(test.testcase_path, "assignment_config"))
    data_path = os.path.join(test.testcase_path, "data")
    if (not os.path.exists(data_path)):
        if os.path.isdir(data_path):
            shutil.rmtree(data_path)
        os.mkdir(data_path)
    shutil.copyfile(os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "config.json"),
                    os.path.join(test.testcase_path, "assignment_config", "config.json"))


############################################################################

def cleanup(test):
    data_dir = os.path.join(test.testcase_path, "data")
    for filename in os.listdir(data_dir):
        file_path = os.path.join(data_dir, filename)
        if os.path.isfile(file_path) or os.path.islink(file_path):
            os.unlink(file_path)
        elif os.path.isdir(file_path):
            shutil.rmtree(file_path)

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
    shutil.copyfile(os.path.join(SAMPLE_SUBMISSIONS, "solution.py"),
                    os.path.join(test.testcase_path, "data", "solution.py"))
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_correct", "-b")
    test.json_diff("results.json", "results.json_correct")



@testcase
def buggy(test):
    cleanup(test)
    shutil.copyfile(os.path.join(SAMPLE_SUBMISSIONS, "buggy.py"),
                    os.path.join(test.testcase_path, "data", "buggy.py"))
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_buggy", "-b")
    test.json_diff("results.json", "results.json_buggy")


@testcase
def syntax_error(test):
    cleanup(test)
    shutil.copyfile(os.path.join(SAMPLE_SUBMISSIONS, "syntax_error.py"),
                    os.path.join(test.testcase_path, "data", "syntax_error.py"))
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_syntax_error", "-b")
    test.json_diff("results.json", "results.json_syntax_error")
