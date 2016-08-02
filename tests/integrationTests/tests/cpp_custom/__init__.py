# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob


############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/sample_files/sample_assignment_config/cpp_custom"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/sample_files/sample_submissions/cpp_custom"

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
    test.diff(".submit.grade",".submit.grade_correct")
    test.json_diff("submission.json","submission.json_correct")


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
    test.diff(".submit.grade",".submit.grade_missing_label")
    test.json_diff("submission.json","submission.json_missing_label")


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
    test.diff(".submit.grade",".submit.grade_wrong_num")
    test.json_diff("submission.json","submission.json_wrong_num")


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
    test.diff(".submit.grade",".submit.grade_wrong_total")
    test.json_diff("submission.json","submission.json_wrong_total")


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
    test.diff(".submit.grade",".submit.grade_not_random")
    test.json_diff("submission.json","submission.json_not_random")


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
    test.diff(".submit.grade",".submit.grade_all_bugs")
    test.json_diff("submission.json","submission.json_all_bugs")


