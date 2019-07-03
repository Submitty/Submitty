# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_TUTORIAL_DIR

import subprocess
import os
import glob
import shutil
from submitty_utils import submitty_schema_validator

############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_TUTORIAL_DIR + "/examples/06_loop_types/config"
SAMPLE_SUBMISSIONS = SUBMITTY_TUTORIAL_DIR + "/examples/06_loop_types/submissions"

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


def cleanup(test):
    subprocess.call(["rm"] + ["-rf"] +
            glob.glob(os.path.join(test.testcase_path, "data", "*")))

@testcase
def schema_validation(test):
    cleanup(test)
    config_path = os.path.join(test.testcase_path, 'assignment_config', 'complete_config.json')
    schema = os.path.join(SUBMITTY_INSTALL_DIR, 'bin', 'json_schemas', 'complete_config_schema.json')
    try:
        submitty_schema_validator.validate_complete_config_schema_using_filenames(config_path, schema, show_warnings=False)
    except submitty_schema_validator.SubmittySchemaException as s:
        s.print_human_readable_error()
        raise

@testcase
def solution(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "solution.py"),
                     os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()

    test.diff("grade.txt", "grade.txt_solution", "-b")
    test.json_diff("results.json", "results.json_solution")



@testcase
def buggy(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "buggy.py"),
                     os.path.join(test.testcase_path, "data")])
    test.run_run()
    test.run_validator()

    test.diff("grade.txt", "grade.txt_buggy", "-b")
    test.json_diff("results.json", "results.json_buggy")


