# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob


############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/python_linehighlight/config"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/python_linehighlight/submissions"

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
        os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_input", "gettysburg_address.txt"),
        os.path.join(test.testcase_path, "data")])
    subprocess.call(["cp",
        os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_output", "output_instructor.txt"),
        os.path.join(test.testcase_path, "data")])

    subprocess.call(["cp"] +
            glob.glob(os.path.join(SAMPLE_SUBMISSIONS, "*.py")) +
            [os.path.join(test.testcase_path, "data")])


############################################################################


@testcase
def run_test(test):
    test.run_run()
    test.diff("test01_output_correct.txt","data/output_instructor.txt")
    test.diff("test02_output_duplicates.txt","duplicate_lines.txt")
    test.diff("test03_output_duplicates.txt","duplicate_lines.txt")
    test.diff("test04_output_extra.txt","extra_lines.txt")
    test.diff("test05_output_extra.txt","extra_lines.txt")
    test.diff("test06_output_missing.txt","missing_lines.txt")
    test.diff("test07_output_missing.txt","missing_lines.txt")
    test.diff("test08_output_reordered.txt","output_reordered.txt")
    test.diff("test09_output_reordered.txt","output_reordered.txt")
    test.run_validator()
    test.json_diff("results.json")
    for i in range(1, 10):
        test.json_diff("test0%d_0_diff.json" % i)
        test.empty_file("test0%d_STDERR.txt" % i)
        test.empty_file("test0%d_STDOUT.txt" % i)
        test.empty_file("test0%d_execute_logfile.txt" % i)
