#/usr/bin/python2

import sys
import os
import glob
import subprocess


tests_dir = "__INSTALL__FILLIN__HSS_REPOSITORY__/tests/integrationTests/tests"
grading_source_dir = "__INSTALL__FILLIN__HSS_INSTALL_DIR__/src/grading"
autograde_install_dir = "__INSTALL__FILLIN__HSS_DATA_DIR__/autograde_tests/"


# Install the tests themselves to /var/local
subprocess.call(["rsync",
    tests_dir, # Source files
    autograde_install_dir, # Destination
    "-r", "--delete"]) # Copy recursively, delete extraneous files

# Fetch source relative to scripts directory, and install it to /var/local
subprocess.call(["rsync"] +
        glob.glob(os.path.join(grading_source_dir, "*.cpp")) +
        [autograde_install_dir+"src", "-r", "--delete"])
subprocess.call(["rsync"] +
        glob.glob(os.path.join(grading_source_dir, "*.hpp")) +
        [autograde_install_dir+"src", "-r", "--delete"])
subprocess.call(["rsync"] +
        glob.glob(os.path.join(grading_source_dir, "*.h")) +
        [autograde_install_dir+"src", "-r", "--delete"])

