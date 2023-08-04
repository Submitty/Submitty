# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob
import shutil
import traceback

############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = os.path.join(SUBMITTY_INSTALL_DIR, "more_autograding_examples",
                                        "c_malloc_not_allowed", "config")
SAMPLE_SUBMISSIONS = os.path.join(SUBMITTY_INSTALL_DIR, "more_autograding_examples",
                                  "c_malloc_not_allowed", "submissions")


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
    subprocess.call(["rm", "-rf",
                     os.path.join(test.testcase_path, "data")])
    os.mkdir(os.path.join(test.testcase_path, "data"))
    os.mkdir(os.path.join(test.testcase_path, "data", "test_output"))


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
def malloc(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "malloc.c"),
                     os.path.join(test.testcase_path, "data", "malloc.c")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.malloc.txt", "-b")
    subprocess.call(["rm", os.path.join(test.testcase_path, "data", "malloc.c")])
    test.json_diff("results.json", "results.malloc.json")


@testcase
def calloc(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "calloc.c"),
                     os.path.join(test.testcase_path, "data", "calloc.c")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.calloc.txt", "-b")
    subprocess.call(["rm", os.path.join(test.testcase_path, "data", "calloc.c")])
    test.json_diff("results.json", "results.calloc.json")


@testcase
def bracket(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "sqbrackets.c"),
                     os.path.join(test.testcase_path, "data", "sqbrackets.c")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.sqbrackets.txt", "-b")
    subprocess.call(["rm", os.path.join(test.testcase_path, "data", "sqbrackets.c")])
    test.json_diff("results.json", "results.sqbrackets.json")


@testcase
def compile_err(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "compile_err.c"),
                     os.path.join(test.testcase_path, "data", "compile_err.c")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.compile_err.txt", "-b")
    subprocess.call(["rm", os.path.join(test.testcase_path, "data", "compile_err.c")])
    test.json_diff("results.json", "results.compile_err.json")
