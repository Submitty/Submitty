# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob
import shutil
import traceback

############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/cpp_provided_code/config"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/cpp_provided_code/submissions"

@prebuild
def initialize(test):
    data_path = os.path.join(test.testcase_path, "data")
    if os.path.isdir(data_path):
        shutil.rmtree(data_path)
    os.mkdir(data_path)

    if os.path.isdir(os.path.join(test.testcase_path, "assignment_config")):
        shutil.rmtree(os.path.join(test.testcase_path, "assignment_config"))
    shutil.copytree(SAMPLE_ASSIGNMENT_CONFIG,
                    os.path.join(test.testcase_path, "assignment_config"))



############################################################################

def cleanup(test):
    if os.path.isdir(os.path.join(test.testcase_path, "data")):
        shutil.rmtree(os.path.join(test.testcase_path, "data"))
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
def solution(test):
    cleanup(test)
    shutil.copy(os.path.join(SAMPLE_SUBMISSIONS, "student.h"),
                os.path.join(test.testcase_path, "data"))
    shutil.copy(os.path.join(SAMPLE_SUBMISSIONS, "student.cpp"),
                os.path.join(test.testcase_path, "data"))
    for f in glob.glob(os.path.join(test.testcase_path,"assignment_config","provided_code","*")):
        shutil.copy(f,os.path.join(test.testcase_path, "data"))
    test.run_compile()
    test.run_run()
    shutil.copy(os.path.join(test.testcase_path,"assignment_config","test_output","output.txt"),
                os.path.join(test.testcase_path, "data", "test_output"))
    test.run_validator()
    test.diff("grade.txt", "grade.txt_solution", "-b")
    test.json_diff("results.json", "results.json_solution")








