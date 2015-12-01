#!/usr/bin/python2

import sys
import os

import lib

# Ensure the correct paths for loading modules are recognized by
# Python. We require both the test packages themselves (which are
# are stored outside of the repository), as well as the "lib"
# module residing within the repository in the scripts directory.

# Use an absolute path for the installed test packages
sys.path.insert(0, "/var/local/hss/autograde_tests")

# The directory containing lib.py should be the same as the one
# that contains this file.
sys.path.insert(0, os.path.dirname(os.path.realpath(sys.argv[0])))

# Load all test packages, which will populate the dictionary in
# the "lib" module.
import tests

# If we were given an argument, run only the test with that name.
# Otherwise, run every test.
if len(sys.argv) == 1:
    lib.run_all()
else:
    lib.run_test(sys.argv[1])
