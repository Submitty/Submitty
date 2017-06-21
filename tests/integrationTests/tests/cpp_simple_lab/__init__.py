# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob


############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/sample_files/sample_assignment_config/cpp_simple_lab"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/sample_files/sample_submissions/cpp_simple_lab"

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

    for i in [str(n) for n in range(1, 3)]:
        try:
            os.mkdir(os.path.join(test.testcase_path, "data", "part" + i))
        except OSError:
            pass

    subprocess.call(["cp"] +
        glob.glob(os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_input", "*.txt")) +
        [os.path.join(test.testcase_path, "data")])

    subprocess.call(["cp"] +
        glob.glob(os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_output", "*.txt")) +
        [os.path.join(test.testcase_path, "data")])


############################################################################

def cleanup(test):
    subprocess.call(["rm"] + ["-f"] +
        glob.glob(os.path.join(test.testcase_path, "data", "result*")))
    subprocess.call(["rm"] + ["-f"] +
        glob.glob(os.path.join(test.testcase_path, "data", "test*")))
    subprocess.call(["rm"] + ["-f"] +
        glob.glob(os.path.join(test.testcase_path, "data", "part*", "*")))
    subprocess.call(["rm"] + ["-f"] +
        glob.glob(os.path.join(test.testcase_path, "data", "*out")))

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
    test.diff("results_grade.txt", "full_credit_results_grade.txt", "-b")
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
    test.diff("results_grade.txt","extra_credit_results_grade.txt","-b")
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
    test.diff("results_grade.txt","warning_results_grade.txt","-b")
    test.json_diff("results.json","warning_results.json")

@testcase
def missing_README(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "julian_full_credit.cpp"),
                     os.path.join(test.testcase_path, "data", "part2")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("results_grade.txt","missing_README_results_grade.txt","-b")
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
    test.diff("results_grade.txt","compile_error_results_grade.txt","-b")
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
    test.diff("results_grade.txt","buggy_results_grade.txt","-b")
    test.json_diff("results.json","buggy_results.json")














