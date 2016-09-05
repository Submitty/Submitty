# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob

############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/sample_files/sample_assignment_config/cpp_goto_auto"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/sample_files/sample_submissions/cpp_goto_auto"

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
    subprocess.call(["cp",
        os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "config.json"),
        os.path.join(test.testcase_path, "assignment_config")])


@testcase
def correct(test):
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "correct.cpp"),
                     os.path.join(test.testcase_path, "data", "correct.cpp")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("results_grade.txt", "results_grade.txt_correct", "-b")
    subprocess.call(["rm", os.path.join(test.testcase_path, "data", "correct.cpp")])


@testcase
def buggy(test):
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "buggy.cpp"),
                     os.path.join(test.testcase_path, "data", "buggy.cpp")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("results_grade.txt", "results_grade.txt_buggy", "-b")
    subprocess.call(["rm", os.path.join(test.testcase_path, "data", "buggy.cpp")])
