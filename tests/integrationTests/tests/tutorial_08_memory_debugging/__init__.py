# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_TUTORIAL_DIR

import subprocess
import os
import glob
import shutil
import traceback

############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_TUTORIAL_DIR + "/examples/08_memory_debugging/config"
SAMPLE_SUBMISSIONS = SUBMITTY_TUTORIAL_DIR + "/examples/08_memory_debugging/submissions/"

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
    except OSError:
        pass

    subprocess.call(["cp",
        os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "config.json"),
        os.path.join(test.testcase_path, "assignment_config")])


############################################################################

def cleanup(test):
    subprocess.call(["rm"] + ["-rf"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "test*")))
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "results*")))

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
        os.path.join(SAMPLE_SUBMISSIONS, "solution.cpp"),
        os.path.join(test.testcase_path, "data/code.cpp")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","grade.txt_solution","-b")


@testcase
def buggy(test):
    cleanup(test)
    subprocess.call(["cp",
        os.path.join(SAMPLE_SUBMISSIONS, "buggy.cpp"),
        os.path.join(test.testcase_path, "data/code.cpp")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","grade.txt_buggy","-b")



