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
    subprocess.call(["cp",
                     os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "config.json"),
                     os.path.join(test.testcase_path, "assignment_config")])


def cleanup(test):
    subprocess.call(["rm", "-rf",
                     os.path.join(test.testcase_path, "data")])
    os.mkdir(os.path.join(test.testcase_path, "data"))
    subprocess.call(["cp"] +
                     glob.glob(os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_output","*.txt")) +
                     [os.path.join(test.testcase_path, "data")])


@testcase
def correct(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "correct.c"),
                     os.path.join(test.testcase_path, "data", "correct.c")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_correct", "-b")


@testcase
def buggy(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "buggy.c"),
                     os.path.join(test.testcase_path, "data", "buggy.c")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_buggy", "-b")
    subprocess.call(["rm", os.path.join(test.testcase_path, "data", "buggy.c")])


@testcase
def alternate(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "alternate.c"),
                     os.path.join(test.testcase_path, "data", "alternate.c")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_alternate", "-b")
    subprocess.call(["rm", os.path.join(test.testcase_path, "data", "alternate.c")])


@testcase
def hello_world(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "hello_world.c"),
                     os.path.join(test.testcase_path, "data", "hello_world.c")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_hello_world", "-b")
    subprocess.call(["rm", os.path.join(test.testcase_path, "data", "hello_world.c")])
