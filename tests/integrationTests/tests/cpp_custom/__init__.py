# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob


############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/cpp_custom/config"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/cpp_custom/submissions"

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
        os.mkdir(os.path.join(test.testcase_path, "build"))
    except OSError:
        pass
    try:
        os.mkdir(os.path.join(test.testcase_path, "build/custom_grader_code"))
    except OSError:
        pass
    subprocess.call(["cp",
                     os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "config.json"),
                     os.path.join(test.testcase_path, "assignment_config")])
    subprocess.call(["cp",
                     os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "instructor_CMakeLists.txt"),
                     os.path.join(test.testcase_path, "build")])
    subprocess.call(["cp",
                     os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "custom_grader_code", "grader.cpp"),
                     os.path.join(test.testcase_path, "build/custom_grader_code")])


############################################################################


@testcase
def correct(test):
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data/", "*.cpp")))
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "correct.cpp"),
                     os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","grade.txt_correct","-b")
    test.json_diff("results.json","results.json_correct")


@testcase
def missing_label(test):
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data/", "*.cpp")))
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "missing_label.cpp"),
                     os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","grade.txt_missing_label","-b")
    test.json_diff("results.json","results.json_missing_label")


@testcase
def wrong_num(test):
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data/", "*.cpp")))
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "wrong_num.cpp"),
                     os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","grade.txt_wrong_num","-b")
    test.json_diff("results.json","results.json_wrong_num")


@testcase
def wrong_total(test):
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data/", "*.cpp")))
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "wrong_total.cpp"),
                     os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","grade.txt_wrong_total","-b")
    test.json_diff("results.json","results.json_wrong_total")


@testcase
def not_random(test):
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data/", "*.cpp")))
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "not_random.cpp"),
                     os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","grade.txt_not_random","-b")
    test.json_diff("results.json","results.json_not_random")


@testcase
def all_bugs(test):
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data/", "*.cpp")))
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "all_bugs.cpp"),
                     os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","grade.txt_all_bugs","-b")
    test.json_diff("results.json","results.json_all_bugs")


