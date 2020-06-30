# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_TUTORIAL_DIR

import subprocess
import os
import glob
import shutil


############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/notebook_basic/config"

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
    subprocess.call(["cp", "-r", test.testcase_path])


@testcase
def schema_validation(test):
    cleanup(test)
    config_path = os.path.join(test.testcase_path, 'assignment_config', 'complete_config.json')
    test.validate_complete_config(config_path)
