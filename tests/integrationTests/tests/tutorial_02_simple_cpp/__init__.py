# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_TUTORIAL_DIR

import subprocess
import os
import glob


############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_TUTORIAL_DIR + "/examples/02_simple_cpp/config"
SAMPLE_SUBMISSIONS = SUBMITTY_TUTORIAL_DIR + "/examples/02_simple_cpp/submissions/"

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
    subprocess.call(["cp",
        os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_output", "test1_output.txt"),
        os.path.join(test.testcase_path, "data")])

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
    test.json_diff("results.json", "results.json_solution")

@testcase
def buggy(test):
    subprocess.call(["cp",
        os.path.join(SAMPLE_SUBMISSIONS, "buggy.cpp"),
        os.path.join(test.testcase_path, "data/code.cpp")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","grade.txt_buggy","-b")
    test.json_diff("results.json", "results.json_buggy")
    test.diff("test02_0_diff.json","test02_0_diff.json_buggy")

