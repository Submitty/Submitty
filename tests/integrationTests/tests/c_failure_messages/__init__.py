# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob

############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/c_failure_messages/config"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/c_failure_messages/submissions"

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
                     os.path.join(SAMPLE_SUBMISSIONS, "correct.c"),
                     os.path.join(test.testcase_path, "data", "correct.c")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("results_grade.txt", "results_grade.txt_correct", "-b")
    subprocess.call(["rm", os.path.join(test.testcase_path, "data", "correct.c")])


@testcase
def buggy(test):
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "buggy.c"),
                     os.path.join(test.testcase_path, "data", "buggy.c")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("results_grade.txt", "results_grade.txt_buggy", "-b")
    subprocess.call(["rm", os.path.join(test.testcase_path, "data", "buggy.c")])


@testcase
def alternate(test):
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "alternate.c"),
                     os.path.join(test.testcase_path, "data", "alternate.c")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("results_grade.txt", "results_grade.txt_alternate", "-b")
    subprocess.call(["rm", os.path.join(test.testcase_path, "data", "alternate.c")])


@testcase
def hello_world(test):
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "hello_world.c"),
                     os.path.join(test.testcase_path, "data", "hello_world.c")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("results_grade.txt", "results_grade.txt_hello_world", "-b")
    subprocess.call(["rm", os.path.join(test.testcase_path, "data", "hello_world.c")])
