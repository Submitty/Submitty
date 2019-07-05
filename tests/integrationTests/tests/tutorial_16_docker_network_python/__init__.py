# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR, SUBMITTY_TUTORIAL_DIR

import subprocess
import os
import glob
import shutil
from submitty_utils import submitty_schema_validator


############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_TUTORIAL_DIR + "/examples/16_docker_network_python/config"
SAMPLE_SUBMISSIONS = SUBMITTY_TUTORIAL_DIR + "/examples/16_docker_network_python/submissions/"

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
    data_path = os.path.join(test.testcase_path, "data")
    if os.path.isdir(data_path):
        shutil.rmtree(data_path)
    os.mkdir(data_path)
    
    subprocess.call(["cp", "-r",
        os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_input"),
        os.path.join(data_path)])
    subprocess.call(["cp"] +
                     glob.glob(os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_output","*")) +
                     [os.path.join(test.testcase_path, "data")])


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

#This test is not possible until lib.py starts using grade_item_main_runner.
@testcase
def correct(test):
  pass
    # cleanup(test)
    # subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "correct","server.py"),
    #                  os.path.join(test.testcase_path, "data")])
    # test.run_compile()
    # test.run_run()
    # test.run_validator()
    # test.diff("grade.txt","correct_grade.txt","-b")
    # test.json_diff("results.json","correct_results.json")
