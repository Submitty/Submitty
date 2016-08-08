# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob


############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/sample_files/sample_assignment_config/python_simple_homework"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/sample_files/sample_submissions/python_simple_homework"

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
        os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_output", "hw01part1_sol.txt"),
        os.path.join(test.testcase_path, "data")])


############################################################################


@testcase
def correct(test):
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data/", "*.py")))
    subprocess.call(["cp",
        os.path.join(SAMPLE_SUBMISSIONS, "part1.py"),
        os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    test.diff("test01_STDOUT.txt","data/hw01part1_sol.txt")
    test.empty_file("test01_STDERR.txt")
    test.empty_json_diff("test01_0_diff.json")
    test.diff(".submit.grade",".submit.grade_correct")
    test.json_diff("submission.json","submission.json_correct")


@testcase
def buggy(test):
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data/", "*.py")))
    subprocess.call(["cp",
        os.path.join(SAMPLE_SUBMISSIONS, "part1_buggy.py"),
        os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    test.diff("test01_STDOUT.txt","test01_STDOUT.txt_buggy")
    test.empty_file("test01_STDERR.txt")
    test.json_diff("test01_0_diff.json","test01_0_diff.json_buggy")
    test.diff(".submit.grade",".submit.grade_buggy")
    test.json_diff("submission.json","submission.json_buggy")


@testcase
def buggy2(test):
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data/", "*.py")))
    subprocess.call(["cp",
        os.path.join(SAMPLE_SUBMISSIONS, "part1_buggy2.py"),
        os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    test.diff("test01_STDOUT.txt","test01_STDOUT.txt_buggy2")
    test.empty_file("test01_STDERR.txt")
    test.json_diff("test01_0_diff.json","test01_0_diff.json_buggy2")
    test.diff(".submit.grade",".submit.grade_buggy2")
    test.json_diff("submission.json","submission.json_buggy2")


@testcase
def syntax_error(test):
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data/", "*.py")))
    subprocess.call(["cp",
        os.path.join(SAMPLE_SUBMISSIONS, "syntax_error.py"),
        os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    test.diff("test01_STDOUT.txt","test01_STDOUT.txt_syntax_error")
    test.diff("test01_STDERR.txt","test01_STDERR.txt_syntax_error")
    test.diff("test01_execute_logfile.txt","test01_execute_logfile.txt_syntax_error")
    test.json_diff("test01_0_diff.json","test01_0_diff.json_syntax_error")
    test.diff(".submit.grade",".submit.grade_syntax_error")
    test.json_diff("submission.json","submission.json_syntax_error")
    # cleanup the non empty logfile
    subprocess.call(["rm",
        os.path.join(test.testcase_path, "data/", "test01_execute_logfile.txt")])


@testcase
def infinite_loop_too_much_output(test):
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data/", "*.py")))
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data/", "test01*.txt")))
    subprocess.call(["cp",
        os.path.join(SAMPLE_SUBMISSIONS, "infinite_loop_too_much_output.py"),
        os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    # truncate the output before comparing (tolerate changes in the file size cutoff)
    test.diff_truncate(100,"test01_STDOUT.txt","test01_STDOUT.txt_infinite_loop_too_much_output")
    test.diff("test01_STDERR.txt","test01_STDERR.txt_infinite_loop_too_much_output")
    test.diff("test01_execute_logfile.txt","test01_execute_logfile.txt_infinite_loop_too_much_output")
    test.diff(".submit.grade",".submit.grade_infinite_loop_too_much_output")
    test.json_diff("submission.json","submission.json_infinite_loop_too_much_output")
    # cleanup the non empty logfile
    subprocess.call(["rm",
        os.path.join(test.testcase_path, "data/", "test01_execute_logfile.txt")])


@testcase
def infinite_loop_time_cutoff(test):
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data/", "*.py")))
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data/", "test01*.txt")))
    subprocess.call(["cp",
        os.path.join(SAMPLE_SUBMISSIONS, "infinite_loop_time_cutoff.py"),
        os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    test.empty_file("test01_STDOUT.txt")
    test.empty_file("test01_STDERR.txt")
    test.json_diff("test01_0_diff.json","test01_0_diff.json_time_cutoff")
    test.diff("test01_execute_logfile.txt","test01_execute_logfile.txt_infinite_loop_time_cutoff")
    test.diff(".submit.grade",".submit.grade_infinite_loop_time_cutoff")
    test.json_diff("submission.json","submission.json_infinite_loop_time_cutoff")
    # cleanup the non empty logfile
    subprocess.call(["rm",
        os.path.join(test.testcase_path, "data/", "test01_execute_logfile.txt")])
