# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_TUTORIAL_DIR

import subprocess
import os
import glob

############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_TUTORIAL_DIR + "/examples/04_python_static_analysis/config"
SAMPLE_SUBMISSIONS = SUBMITTY_TUTORIAL_DIR + "/examples/04_python_static_analysis/submissions/"

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
    subprocess.call(["cp",
        os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_output", "output.txt"),
        os.path.join(test.testcase_path, "data")])


def cleanup(test):
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data", "*")))
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data", "test*")))


@testcase
def correct(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "solution.py"),
                     os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_correct", "-b")
    test.json_diff("results.json", "results.json_correct")



@testcase
def buggy(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "buggy.py"),
                     os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_buggy", "-b")
    test.json_diff("results.json", "results.json_buggy")


@testcase
def buggy2(test):
    cleanup(test)
    # FIXME: INFINITE LOOP(?) WHEN THIS DOES NOT EXIST
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "buggy_2.py"),
                     os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_buggy2", "-b")
    test.json_diff("results.json", "results.json_buggy2")


@testcase
def buggy3(test):
    cleanup(test)
    # FIXME: INFINITE LOOP(?) WHEN THIS DOES NOT EXIST
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "buggy_3.py"),
                     os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_buggy3", "-b")
    test.json_diff("results.json", "results.json_buggy3")
