# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR, SUBMITTY_TUTORIAL_DIR

import subprocess
import os
import glob
import shutil

############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_TUTORIAL_DIR + "/examples/10_java_coverage/config"
SAMPLE_SUBMISSIONS       = SUBMITTY_TUTORIAL_DIR + "/examples/10_java_coverage/submissions/"

@prebuild
def initialize(test):
    try:
        os.mkdir(os.path.join(test.testcase_path, "assignment_config"))
    except OSError:
        pass
    subprocess.call(["cp",
                     os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "config.json"),
                     os.path.join(test.testcase_path, "assignment_config")])


############################################################################

def cleanup(test):
    try:
        shutil.rmtree(test.testcase_path + "/data")
    except OSError:
        pass
    os.mkdir(os.path.join(test.testcase_path, "data"))
    subprocess.call(["cp", "-r",
        os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_code"),
        os.path.join(test.testcase_path, "data")])


############################################################################

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
    test.diff("grade.txt","correct_grade.txt","-b")
    test.json_diff("results.json","correct_results.json")
    test.emma_coverage_diff("test05_emma_report.txt","correct_test05_emma_report.txt")


@testcase
def correct_no_coverage(test):
    cleanup(test)
    subprocess.call(["cp",
        os.path.join(SAMPLE_SUBMISSIONS, "correct_no_coverage.zip"),
        os.path.join(test.testcase_path, "data/")])
    subprocess.call(["unzip",
                     "-q",  # quiet
                     "-o",  # overwrite files
                     os.path.join(test.testcase_path, "data/correct_no_coverage.zip"),
                     "-d",  # save to directory
                     os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","correct_no_coverage_grade.txt","-b")
    test.json_diff("results.json","correct_no_coverage_results.json")
    test.emma_coverage_diff("test05_emma_report.txt","correct_no_coverage_test05_emma_report.txt")


@testcase
def buggy_coverage(test):
    cleanup(test)
    subprocess.call(["cp",
        os.path.join(SAMPLE_SUBMISSIONS, "buggy_coverage.zip"),
        os.path.join(test.testcase_path, "data/")])
    subprocess.call(["unzip",
                     "-q",  # quiet
                     "-o",  # overwrite files
                     os.path.join(test.testcase_path, "data/buggy_coverage.zip"),
                     "-d",  # save to directory
                     os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "buggy_coverage_grade.txt", "-b")
    test.json_diff("results.json", "buggy_coverage_results.json")
    test.emma_coverage_diff("test05_emma_report.txt", "buggy_coverage_test05_emma_report.txt")


@testcase
def buggy_no_coverage(test):
    cleanup(test)
    subprocess.call(["cp",
        os.path.join(SAMPLE_SUBMISSIONS, "buggy_no_coverage.zip"),
        os.path.join(test.testcase_path, "data/")])
    subprocess.call(["unzip",
                     "-q",  # quiet
                     "-o",  # overwrite files
                     os.path.join(test.testcase_path, "data/buggy_no_coverage.zip"),
                     "-d",  # save to directory
                     os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","buggy_no_coverage_grade.txt","-b")
    test.json_diff("results.json","buggy_no_coverage_results.json")
    test.emma_coverage_diff("test05_emma_report.txt","buggy_no_coverage_test05_emma_report.txt")


