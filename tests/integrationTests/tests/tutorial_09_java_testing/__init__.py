# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_TUTORIAL_DIR

import subprocess
import os
import glob


############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_TUTORIAL_DIR + "/examples/09_java_testing/config"
SAMPLE_SUBMISSIONS = SUBMITTY_TUTORIAL_DIR + "/examples/09_java_testing/submissions/"

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
                    glob.glob(os.path.join(test.testcase_path, "data/", "*.zip")))
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data/", "Factorial.class")))
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data/", "Factorial.java")))
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data/", "test*.txt")))
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data/grade.txt")))
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data/results.json")))


@testcase
def correct(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "correct.zip"),
                     os.path.join(test.testcase_path, "data/")])
    subprocess.call(["unzip",
                     "-q",  # quiet
                     "-o",  # overwrite files
                     os.path.join(test.testcase_path, "data/correct.zip"),
                     "-d",  # save to directory
                     os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.empty_file("test01_STDOUT.txt")
    test.empty_file("test01_STDERR.txt")
    test.empty_file("test02_STDOUT.txt")
    test.empty_file("test02_STDERR.txt")
    test.junit_diff("test03_STDOUT.txt", "correct_test03_STDOUT.txt")
    test.empty_file("test03_STDERR.txt")
    test.empty_file("test03_execute_logfile.txt")
    test.diff("grade.txt", "correct_grade.txt", "-b")
    test.json_diff("results.json", "correct_results.json")


@testcase
def does_not_compile(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "does_not_compile.zip"),
                     os.path.join(test.testcase_path, "data/")])
    subprocess.call(["unzip",
                     "-q",  # quiet
                     "-o",  # overwrite files
                     os.path.join(test.testcase_path, "data/does_not_compile.zip"),
                     "-d",  # save to directory
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
    test.diff("grade.txt", "does_not_compile_grade.txt", "-b")
    test.json_diff("results.json", "does_not_compile_results.json")


@testcase
def buggy(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "buggy.zip"),
                     os.path.join(test.testcase_path, "data/")])
    subprocess.call(["unzip",
                     "-q",  # quiet
                     "-o",  # overwrite files
                     os.path.join(test.testcase_path, "data/buggy.zip"),
                     "-d",  # save to directory
                     os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.empty_file("test01_STDOUT.txt")
    test.empty_file("test01_STDERR.txt")
    test.empty_file("test02_STDOUT.txt")
    test.empty_file("test02_STDERR.txt")
    test.junit_diff("test03_STDOUT.txt", "buggy_test03_STDOUT.txt")
    test.empty_file("test03_STDERR.txt")
    test.diff("test03_execute_logfile.txt", "exit_status_1.txt")
    test.diff("grade.txt", "buggy_grade.txt", "-b")
    test.json_diff("results.json", "buggy_results.json")


@testcase
def still_buggy(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "still_buggy.zip"),
                     os.path.join(test.testcase_path, "data/")])
    subprocess.call(["unzip",
                     "-q",  # quiet
                     "-o",  # overwrite files
                     os.path.join(test.testcase_path, "data/still_buggy.zip"),
                     "-d",  # save to directory
                     os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.empty_file("test01_STDOUT.txt")
    test.empty_file("test01_STDERR.txt")
    test.empty_file("test02_STDOUT.txt")
    test.empty_file("test02_STDERR.txt")
    test.junit_diff("test03_STDOUT.txt", "still_buggy_test03_STDOUT.txt")
    test.empty_file("test03_STDERR.txt")
    test.diff("test03_execute_logfile.txt", "exit_status_1.txt")
    test.diff("grade.txt", "still_buggy_grade.txt", "-b")
    test.json_diff("results.json", "still_buggy_results.json")
