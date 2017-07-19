# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_TUTORIAL_DIR

import subprocess
import os
import glob


############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_TUTORIAL_DIR + "/examples/11_resources/config"
SAMPLE_SUBMISSIONS = SUBMITTY_TUTORIAL_DIR + "/examples/11_resources/submissions/"

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
                     os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_output", "simple_out.txt"),
                     os.path.join(test.testcase_path, "data")])


############################################################################


def cleanup(test):
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "*cpp")))
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "test*")))
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "results*")))


@testcase
def solution(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "solution.cpp"),
                     os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","solution_grade.txt","-b")
    test.json_diff("results.json","solution_results.json")
    test.diff("test02_STDOUT.txt","data/simple_out.txt")
    test.empty_file("test02_STDERR.txt")
    test.empty_file("test02_execute_logfile.txt")


@testcase
def buggy(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "buggy.cpp"),
                     os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","buggy_grade.txt","-b")
    test.json_diff("results.json","buggy_results.json")
    test.empty_file("test02_STDOUT.txt")
    test.empty_file("test02_STDERR.txt")
    test.diff("test02_execute_logfile.txt","buggy_test02_execute_logfile.txt")

