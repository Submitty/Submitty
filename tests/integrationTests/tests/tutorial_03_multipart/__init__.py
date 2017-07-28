# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_TUTORIAL_DIR

import subprocess
import os
import glob

############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_TUTORIAL_DIR + "/examples/03_multipart/config"
SAMPLE_SUBMISSIONS       = SUBMITTY_TUTORIAL_DIR + "/examples/03_multipart/submissions"

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
    try:
        os.mkdir(os.path.join(test.testcase_path, "data", "part1"))
    except OSError:
        pass
    try:
        os.mkdir(os.path.join(test.testcase_path, "data", "part2"))
    except OSError:
        pass
    try:
        os.mkdir(os.path.join(test.testcase_path, "data", "part3"))
    except OSError:
        pass


def cleanup(test):
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data", "part*", "*")))
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data", "test*")))
    subprocess.call(["cp"] +
                     glob.glob(os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_output","*")) +
                     [os.path.join(test.testcase_path, "data")])


@testcase
def solution(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "part1.py"),
                     os.path.join(test.testcase_path, "data", "part1")])
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "part2.py"),
                     os.path.join(test.testcase_path, "data", "part2")])
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "part3.py"),
                     os.path.join(test.testcase_path, "data", "part3")])
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_solution", "-b")
    test.json_diff("results.json", "results.json_solution")



@testcase
def buggy(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "part1_syntax_error1.py"),
                     os.path.join(test.testcase_path, "data", "part1")])
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "part2_syntax_error1.py"),
                     os.path.join(test.testcase_path, "data", "part2")])
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "part3_syntax_error1.py"),
                     os.path.join(test.testcase_path, "data", "part3")])
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_buggy", "-b")
    test.json_diff("results.json", "results.json_buggy")


@testcase
def buggy2(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "part1_syntax_error2.py"),
                     os.path.join(test.testcase_path, "data", "part1")])
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_buggy2", "-b")
    test.json_diff("results.json", "results.json_buggy2")


@testcase
def wrong(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "part1_wrong_output.py"),
                     os.path.join(test.testcase_path, "data", "part1")])
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "part2_wrong_output.py"),
                     os.path.join(test.testcase_path, "data", "part2")])
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "part3_wrong_output.py"),
                     os.path.join(test.testcase_path, "data", "part3")])
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_wrong", "-b")
    test.json_diff("results.json", "results.json_wrong")
    
