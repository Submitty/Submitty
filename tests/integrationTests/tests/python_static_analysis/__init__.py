# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob

############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/sample_files/sample_assignment_config/python_static_analysis"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/sample_files/sample_submissions/python_static_analysis"

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
    for i in [str(n) for n in range(1, 5)]:
        try:
            os.mkdir(os.path.join(test.testcase_path, "data", "part" + i))
        except OSError:
            pass
        subprocess.call(["cp",
            os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_output", "p" + i + "_out.txt"),
            os.path.join(test.testcase_path, "data")])

@testcase
def correct(test):
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data", "part*", "*.py")))
    for i in [str(n) for n in range(1, 5)]:
        subprocess.call(["cp",
            os.path.join(SAMPLE_SUBMISSIONS, "p" + i + "_sol.py"),
            os.path.join(test.testcase_path, "data", "part" + i)])
    test.run_run()
    test.run_validator()
    test.diff(".submit.grade", ".submit.grade_correct")
    test.json_diff("submission.json", "submission.json_correct")

@testcase
def buggy(test):
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data", "part*", "*.py")))
    for i in [str(n) for n in range(1, 5)]:
        subprocess.call(["cp",
            os.path.join(SAMPLE_SUBMISSIONS, "p" + i + "_bug.py"),
            os.path.join(test.testcase_path, "data", "part" + i)])
    test.run_run()
    test.run_validator()
    test.diff(".submit.grade", ".submit.grade_buggy")
    test.json_diff("submission.json", "submission.json_buggy")
