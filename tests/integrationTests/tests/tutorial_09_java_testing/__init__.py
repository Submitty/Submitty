# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_TUTORIAL_DIR

import subprocess
import os
import glob
import shutil
import traceback

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
        data_path = os.path.join(test.testcase_path, "data")
        if os.path.isdir(data_path):
            shutil.rmtree(data_path)
        os.mkdir(data_path)
    except OSError:
        pass

    subprocess.call(["cp",
                     os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "config.json"),
                     os.path.join(test.testcase_path, "assignment_config")])
    subprocess.call(["cp",
                     os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "provided_code", "FactorialTest.java"),
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
    subprocess.call(["rm"] + ["-rf"] +
                    glob.glob(os.path.join(test.testcase_path, "data/", "test*")))
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data/grade.txt")))
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data/results.json")))


@testcase
def schema_validation(test):
    cleanup(test)
    config_path = os.path.join(test.testcase_path, 'assignment_config', 'complete_config.json')
    try:
        test.validate_complete_config(config_path)
    except Exception:
        traceback.print_exc()
        raise

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
    test.empty_file("test01/STDOUT.txt")
    test.diff("test01/STDERR.txt", "not_empty.txt")
    test.empty_file("test02/STDOUT.txt")
    test.diff("test02/STDERR.txt", "not_empty.txt")
    test.junit_diff("test03/STDOUT.txt", "correct_test03_STDOUT.txt")
    test.diff("test03/STDERR.txt", "not_empty.txt")
    test.empty_file("test03/execute_logfile.txt")
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
    test.empty_file("test01/STDOUT.txt")
    test.diff("test01/STDERR.txt", "does_not_compile_test01_STDERR.txt")
    test.empty_file("test02/STDOUT.txt")
    test.diff("test02/STDERR.txt", "does_not_compile_test02_STDERR.txt")
    test.junit_diff("test03/STDOUT.txt", "does_not_compile_test03_STDOUT.txt")
    test.diff("test03/STDERR.txt", "not_empty.txt")
    test.diff("test03/execute_logfile.txt", "exit_status_1.txt")
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
    test.empty_file("test01/STDOUT.txt")
    test.diff("test01/STDERR.txt", "not_empty.txt")
    test.empty_file("test02/STDOUT.txt")
    test.diff("test02/STDERR.txt", "not_empty.txt")
    test.junit_diff("test03/STDOUT.txt", "buggy_test03_STDOUT.txt")
    test.diff("test03/STDERR.txt", "not_empty.txt")
    test.diff("test03/execute_logfile.txt", "exit_status_1.txt")
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
    test.empty_file("test01/STDOUT.txt")
    test.diff("test01/STDERR.txt", "not_empty.txt")
    test.empty_file("test02/STDOUT.txt")
    test.diff("test02/STDERR.txt", "not_empty.txt")
    test.junit_diff("test03/STDOUT.txt", "still_buggy_test03_STDOUT.txt")
    test.diff("test03/STDERR.txt", "not_empty.txt")
    test.diff("test03/execute_logfile.txt", "exit_status_1.txt")
    test.diff("grade.txt", "still_buggy_grade.txt", "-b")
    test.json_diff("results.json", "still_buggy_results.json")
