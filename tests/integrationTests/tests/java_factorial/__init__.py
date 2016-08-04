# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob


############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/sample_files/sample_assignment_config/java_factorial"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/sample_files/sample_submissions/java_factorial"

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
        os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_code", "FactorialTest.java"),
        os.path.join(test.testcase_path, "data")])


############################################################################


def cleanup(test):
    # seem to need to cleanup this class file, otherwise it doesn't recompile
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data/", "Factorial.class")))
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data/", "Factorial.java")))
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data/", "test*.txt")))
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data/.submit.grade")))
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data/submission.json")))


@testcase
def correct(test):
    cleanup(test)
    subprocess.call(["cp",
        os.path.join(SAMPLE_SUBMISSIONS, "correct/Factorial.java"),
        os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.empty_file("test01_STDOUT.txt")
    test.empty_file("test01_STDERR.txt")
    test.empty_file("test02_STDOUT.txt")
    test.empty_file("test02_STDERR.txt")
    test.junit_diff("test03_STDOUT.txt","correct_test03_STDOUT.txt")
    test.empty_file("test03_STDERR.txt")
    test.empty_file("test03_execute_logfile.txt")
    test.diff(".submit.grade","correct_.submit.grade")
    test.json_diff("submission.json","correct_submission.json")


@testcase
def does_not_compile(test):
    cleanup(test)
    subprocess.call(["cp",
        os.path.join(SAMPLE_SUBMISSIONS, "does_not_compile/Factorial.java"),
        os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.empty_file("test01_STDOUT.txt")
    test.diff("test01_STDERR.txt", "does_not_compile_test01_STDERR.txt")
    test.empty_file("test02_STDOUT.txt")
    test.diff("test02_STDERR.txt", "does_not_compile_test02_STDERR.txt")
    test.junit_diff("test03_STDOUT.txt", "does_not_compile_test03_STDOUT.txt")
    test.empty_file("test03_STDERR.txt")
    test.diff("test03_execute_logfile.txt", "exit_status_1.txt")
    test.diff(".submit.grade", "does_not_compile_.submit.grade")
    test.json_diff("submission.json", "does_not_compile_submission.json")


@testcase
def buggy(test):
    cleanup(test)
    subprocess.call(["cp",
        os.path.join(SAMPLE_SUBMISSIONS, "buggy/Factorial.java"),
        os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.empty_file("test01_STDOUT.txt")
    test.empty_file("test01_STDERR.txt")
    test.empty_file("test02_STDOUT.txt")
    test.empty_file("test02_STDERR.txt")
    test.junit_diff("test03_STDOUT.txt","buggy_test03_STDOUT.txt")
    test.empty_file("test03_STDERR.txt")
    test.diff("test03_execute_logfile.txt","exit_status_1.txt")
    test.diff(".submit.grade","buggy_.submit.grade")
    test.json_diff("submission.json","buggy_submission.json")


@testcase
def still_buggy(test):
    cleanup(test)
    subprocess.call(["cp",
        os.path.join(SAMPLE_SUBMISSIONS, "still_buggy/Factorial.java"),
        os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.empty_file("test01_STDOUT.txt")
    test.empty_file("test01_STDERR.txt")
    test.empty_file("test02_STDOUT.txt")
    test.empty_file("test02_STDERR.txt")
    test.junit_diff("test03_STDOUT.txt","still_buggy_test03_STDOUT.txt")
    test.empty_file("test03_STDERR.txt")
    test.diff("test03_execute_logfile.txt","exit_status_1.txt")
    test.diff(".submit.grade","still_buggy_.submit.grade")
    test.json_diff("submission.json","still_buggy_submission.json")

