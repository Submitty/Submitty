# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
from pathlib import Path
import shutil


############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/cpp_simple_lab/config"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/cpp_simple_lab/submissions"

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

    for i in [str(n) for n in range(1, 3)]:
        try:
            os.mkdir(os.path.join(test.testcase_path, "data", "part" + i))
        except OSError:
            pass

    subprocess.call(["cp"] +
        Path(SAMPLE_ASSIGNMENT_CONFIG, "test_input").glob( "*.txt") +
        [os.path.join(test.testcase_path, "data")])

    subprocess.call(["cp"] +
        Path(SAMPLE_ASSIGNMENT_CONFIG, "test_output").glob( "*.txt") +
        [os.path.join(test.testcase_path, "data")])


############################################################################

def cleanup(test):
    subprocess.call(["rm"] + ["-f"] +
        Path(test.testcase_path, "data").glob( "result*"))
    subprocess.call(["rm"] + ["-rf"] +
        Path(test.testcase_path, "data").glob( "test*"))
    subprocess.call(["rm"] + ["-f"] +
        Path(test.testcase_path, "data", "part*").glob( "*"))
    subprocess.call(["rm"] + ["-f"] +
        Path(test.testcase_path, "data").glob( "*out"))

@testcase
def full_credit(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "README.txt"),
                     os.path.join(test.testcase_path, "data", "part1")])

    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "julian_full_credit.cpp"),
                     os.path.join(test.testcase_path, "data", "part2")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "full_credit_grade.txt", "-b")
    test.json_diff("results.json", "full_credit_results.json")

@testcase
def extra_credit(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "README.txt"),
                     os.path.join(test.testcase_path, "data", "part1")])

    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "julian_extra_credit.cpp"),
                     os.path.join(test.testcase_path, "data", "part2")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","extra_credit_grade.txt","-b")
    test.json_diff("results.json","extra_credit_results.json")

@testcase
def warning(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "README.txt"),
                     os.path.join(test.testcase_path, "data", "part1")])

    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "julian_warning.cpp"),
                     os.path.join(test.testcase_path, "data", "part2")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","warning_grade.txt","-b")
    test.json_diff("results.json","warning_results.json")

@testcase
def missing_README(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "julian_full_credit.cpp"),
                     os.path.join(test.testcase_path, "data", "part2")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","missing_README_grade.txt","-b")
    test.json_diff("results.json","missing_README_results.json")


@testcase
def compilation_error(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "README.txt"),
                     os.path.join(test.testcase_path, "data", "part1")])

    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "julian_compile_error.cpp"),
                     os.path.join(test.testcase_path, "data", "part2")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","compile_error_grade.txt","-b")
    test.json_diff("results.json","compile_error_results.json")

@testcase
def buggy(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "README.txt"),
                     os.path.join(test.testcase_path, "data", "part1")])
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "julian_buggy.cpp"),
                     os.path.join(test.testcase_path, "data", "part2")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","buggy_grade.txt","-b")
    test.json_diff("results.json","buggy_results.json")














