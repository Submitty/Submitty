# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_TUTORIAL_DIR

import subprocess
import os
import glob


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
        os.mkdir(os.path.join(test.testcase_path, "data"))
    except OSError:
        pass
    try:
        os.mkdir(os.path.join(test.testcase_path, "data"))
    except OSError:
        pass

    subprocess.call(["cp",
        os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "config.json"),
        os.path.join(test.testcase_path, "assignment_config")])


############################################################################


@testcase
def solution(test):
    subprocess.call(["cp",
        os.path.join(SAMPLE_SUBMISSIONS, "solution.cpp"),
        os.path.join(test.testcase_path, "data/code.cpp")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","grade.txt_solution","-b")


@testcase
def buggy(test):
    subprocess.call(["cp",
        os.path.join(SAMPLE_SUBMISSIONS, "buggy.cpp"),
        os.path.join(test.testcase_path, "data/code.cpp")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","grade.txt_buggy","-b")



