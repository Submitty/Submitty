# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob
import shutil

############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/choice_of_language/config"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/choice_of_language/submissions"


@prebuild
def initialize(test):
    try:
        os.mkdir(os.path.join(test.testcase_path, "assignment_config"))
    except OSError:
        pass
    subprocess.call(["cp",
        os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "config.json"),
        os.path.join(test.testcase_path, "assignment_config")])



def cleanup(test):
    try:
        shutil.rmtree(os.path.join(test.testcase_path,"data"))
    except Exception:
        pass
    
    os.mkdir(os.path.join(test.testcase_path, "data"))
    os.mkdir(os.path.join(test.testcase_path, "data", "part1"))
    os.mkdir(os.path.join(test.testcase_path, "data", "part2"))
    os.mkdir(os.path.join(test.testcase_path, "data", "part3"))
    os.mkdir(os.path.join(test.testcase_path, "data", "part4"))

    subprocess.call(["cp",
                     os.path.join(SAMPLE_ASSIGNMENT_CONFIG,"test_output", "output.txt"),
                    os.path.join(test.testcase_path, "data")])


@testcase
def python2(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "python2.py"),
                     os.path.join(test.testcase_path, "data", "part1")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_python2", "-b")
    test.json_diff("results.json", "results.json_python2")

    
@testcase
def python3(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "python3.py"),
                     os.path.join(test.testcase_path, "data", "part2")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_python3", "-b")
    test.json_diff("results.json", "results.json_python3")


@testcase
def c(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "c.c"),
                     os.path.join(test.testcase_path, "data", "part3")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_c", "-b")
    test.json_diff("results.json", "results.json_c")


@testcase
def cpp(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "cpp.cpp"),
                     os.path.join(test.testcase_path, "data", "part4")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_cpp", "-b")
    test.json_diff("results.json", "results.json_cpp")

