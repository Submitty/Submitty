# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob
import shutil
import traceback

############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = os.path.join(SUBMITTY_INSTALL_DIR, "more_autograding_examples", "c_system_call_filtering", "config")
SAMPLE_SUBMISSIONS       = os.path.join(SUBMITTY_INSTALL_DIR, "more_autograding_examples", "c_system_call_filtering", "submissions")

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
    for file in glob.glob(os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_output","*.txt")):
        shutil.copy(file, os.path.join(test.testcase_path, "data", "test_output"))


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
def safe(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "safe.c"),
                     os.path.join(test.testcase_path, "data", "safe.c")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_safe", "-b")
    test.json_diff("results.json","results.json_safe")

@testcase
def restricted_scheduling(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "restricted_scheduling.c"),
                     os.path.join(test.testcase_path, "data", "restricted_scheduling.c")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_restricted_scheduling", "-b")
    subprocess.call(["rm", os.path.join(test.testcase_path, "data", "restricted_scheduling.c")])
    test.json_diff("results.json","results.json_restricted_scheduling")

@testcase
def restricted_device_management(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "restricted_device_management.c"),
                     os.path.join(test.testcase_path, "data", "restricted_device_management.c")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_restricted_device_management", "-b")
    subprocess.call(["rm", os.path.join(test.testcase_path, "data", "restricted_device_management.c")])
    test.json_diff("results.json","results.json_restricted_device_management")


@testcase
def forbidden(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "forbidden.c"),
                     os.path.join(test.testcase_path, "data", "forbidden.c")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_forbidden", "-b")
    subprocess.call(["rm", os.path.join(test.testcase_path, "data", "forbidden.c")])
    test.json_diff("results.json","results.json_forbidden")

