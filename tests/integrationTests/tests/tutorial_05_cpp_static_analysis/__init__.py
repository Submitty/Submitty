# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_TUTORIAL_DIR

import subprocess
import os
import glob

############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_TUTORIAL_DIR + "/examples/05_cpp_static_analysis/config"
SAMPLE_SUBMISSIONS = SUBMITTY_TUTORIAL_DIR + "/examples/05_cpp_static_analysis/submissions/"

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
def solution(test):
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "solution.cpp"),
                     os.path.join(test.testcase_path, "data", "solution.cpp")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_solution", "-b")
    subprocess.call(["rm", os.path.join(test.testcase_path, "data", "solution.cpp")])


@testcase
def buggy(test):
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "buggy.cpp"),
                     os.path.join(test.testcase_path, "data", "buggy.cpp")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_buggy", "-b")
    subprocess.call(["rm", os.path.join(test.testcase_path, "data", "buggy.cpp")])


@testcase
def buggy2(test):
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "buggy2.cpp"),
                     os.path.join(test.testcase_path, "data", "buggy2.cpp")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_buggy2", "-b")
    subprocess.call(["rm", os.path.join(test.testcase_path, "data", "buggy2.cpp")])

    
@testcase
def buggy3(test):
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "buggy3.cpp"),
                     os.path.join(test.testcase_path, "data", "buggy3.cpp")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_buggy3", "-b")
    subprocess.call(["rm", os.path.join(test.testcase_path, "data", "buggy3.cpp")])
