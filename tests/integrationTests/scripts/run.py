#!/usr/bin/python2

import sys
import os
import glob
import subprocess

import lib

# Ensure the correct paths for loading modules are recognized by
# Python. We require both the test packages themselves (which are
# are stored outside of the repository), as well as the "lib"
# module residing within the repository in the scripts directory.

tests_dir = "__INSTALL__FILLIN__HSS_REPOSITORY__/tests/integrationTests/tests"
grading_source_dir = "__INSTALL__FILLIN__HSS_INSTALL_DIR__/src/grading"
autograde_install_dir = "__INSTALL__FILLIN__HSS_DATA_DIR__/autograde_tests/"

sys.path.insert(0, autograde_install_dir)

# The directory containing lib.py should be the same as the one
# that contains this file.
sys.path.insert(0, "__INSTALL__FILLIN__HSS_INSTALL_DIR__/bin")

# Load all test packages, which will populate the dictionary in
# the "lib" module.
import tests

# If we were given an argument, run only the test with that name.
# Otherwise, run every test.
if len(sys.argv) == 1:
    lib.run_all()
else:
    lib.run_test(sys.argv[1])
