# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob
import shutil

############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/cpp_provided_code/config"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/cpp_provided_code/submissions"

@prebuild
def initialize(test):

    if os.path.isdir(os.path.join(test.testcase_path, "assignment_config")):
        shutil.rmtree(os.path.join(test.testcase_path, "assignment_config"))
    shutil.copytree(SAMPLE_ASSIGNMENT_CONFIG,
                    os.path.join(test.testcase_path, "assignment_config"))
    


############################################################################

def cleanup(test):
    if os.path.isdir(os.path.join(test.testcase_path, "data")):
        shutil.rmtree(os.path.join(test.testcase_path, "data"))
    os.mkdir(os.path.join(test.testcase_path, "data"))

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
                os.path.join(test.testcase_path, "data"))
    test.run_validator()
    test.diff("grade.txt", "grade.txt_solution", "-b")
    test.json_diff("results.json", "results.json_solution")








