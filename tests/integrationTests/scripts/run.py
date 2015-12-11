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

base_path = os.path.dirname(os.path.realpath(sys.argv[0]))

if os.environ.get("TEST_IN_PLACE") is None:
    # Use an absolute path for the installed test packages
    sys.path.insert(0, "/var/local/hss/autograde_tests")
else:
    run_dir = os.path.join(base_path, "..")
    sys.path.insert(0, run_dir)
    subprocess.call(["rsync"] +
            glob.glob(os.path.join(base_path, "..", "..", "..", "grading", "*")) +
            [os.path.join(run_dir, "src"), "-r", "--delete"])

# The directory containing lib.py should be the same as the one
# that contains this file.
sys.path.insert(0, base_path)

# Load all test packages, which will populate the dictionary in
# the "lib" module.
import tests

# If we were given an argument, run only the test with that name.
# Otherwise, run every test.
if len(sys.argv) == 1:
    lib.run_all()
else:
    lib.run_test(sys.argv[1])
