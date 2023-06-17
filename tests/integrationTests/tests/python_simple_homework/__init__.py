# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob
import shutil
import traceback

############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/python_simple_homework/config"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/python_simple_homework/submissions"

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


############################################################################

def cleanup(test):
    # cleanup the non empty logfile
    subprocess.call(["rm","-f",
        os.path.join(test.testcase_path, "data/", "test01/execute_logfile.txt")])
    subprocess.call(["rm"] + ["-rf"] +
            glob.glob(os.path.join(test.testcase_path, "data", "test*")))

    os.mkdir(os.path.join(test.testcase_path, "data", "test_output"))
    subprocess.call(["cp",
        os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_output", "hw01part1_sol.txt"),
        os.path.join(test.testcase_path, "data", "test_output")])

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
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data", "*.py")))
    subprocess.call(["cp",
        os.path.join(SAMPLE_SUBMISSIONS, "part1.py"),
        os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    test.diff("test01/STDOUT.txt","data/test_output/hw01part1_sol.txt")
    test.empty_file("test01/STDERR.txt")
    test.empty_json_diff("test01/0_diff.json")
    test.diff("grade.txt","grade.txt_correct","-b")
    test.json_diff("results.json","results.json_correct")


@testcase
def buggy(test):
    cleanup(test)
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data/", "*.py")))
    subprocess.call(["cp",
        os.path.join(SAMPLE_SUBMISSIONS, "part1_buggy.py"),
        os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    test.diff("test01/STDOUT.txt","test01_STDOUT.txt_buggy")
    test.empty_file("test01/STDERR.txt")
    test.json_diff("test01/0_diff.json","test01_0_diff.json_buggy")
    test.diff("grade.txt","grade.txt_buggy","-b")
    test.json_diff("results.json","results.json_buggy")


@testcase
def buggy2(test):
    cleanup(test)
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data/", "*.py")))
    subprocess.call(["cp",
        os.path.join(SAMPLE_SUBMISSIONS, "part1_buggy2.py"),
        os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    test.diff("test01/STDOUT.txt","test01_STDOUT.txt_buggy2")
    test.empty_file("test01/STDERR.txt")
    test.json_diff("test01/0_diff.json","test01_0_diff.json_buggy2")
    test.diff("grade.txt","grade.txt_buggy2","-b")
    test.json_diff("results.json","results.json_buggy2")


@testcase
def syntax_error(test):
    cleanup(test)
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data/", "*.py")))
    subprocess.call(["cp",
        os.path.join(SAMPLE_SUBMISSIONS, "syntax_error.py"),
        os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    test.diff("test01/STDOUT.txt","test01_STDOUT.txt_syntax_error")
    test.diff("test01/STDERR.txt","test01_STDERR.txt_syntax_error")
    test.diff("test01/execute_logfile.txt","test01_execute_logfile.txt_syntax_error")
    test.json_diff("test01/0_diff.json","test01_0_diff.json_syntax_error")
    test.diff("grade.txt","grade.txt_syntax_error","-b")
    test.json_diff("results.json","results.json_syntax_error")


@testcase
def infinite_loop_too_much_output(test):
    cleanup(test)
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
    test.diff_truncate(100,"test01/STDOUT.txt","test01_STDOUT.txt_infinite_loop_too_much_output")
    test.diff("test01/STDERR.txt","test01_STDERR.txt_infinite_loop_too_much_output")
    test.diff("test01/execute_logfile.txt","test01_execute_logfile.txt_infinite_loop_too_much_output")
    test.diff("grade.txt","grade.txt_infinite_loop_too_much_output","-b")
    test.json_diff("results.json","results.json_infinite_loop_too_much_output")


@testcase
def infinite_loop_time_cutoff(test):
    cleanup(test)
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data/", "*.py")))
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data/", "test01*.txt")))
    subprocess.call(["cp",
        os.path.join(SAMPLE_SUBMISSIONS, "infinite_loop_time_cutoff.py"),
        os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()
    test.empty_file("test01/STDOUT.txt")
    test.empty_file("test01/STDERR.txt")
    #test.json_diff("test01/0_diff.json","test01/0_diff.json_time_cutoff")
    test.diff("test01/execute_logfile.txt","test01_execute_logfile.txt_infinite_loop_time_cutoff")
    test.diff("grade.txt","grade.txt_infinite_loop_time_cutoff","-b")
    test.json_diff("results.json","results.json_infinite_loop_time_cutoff")
