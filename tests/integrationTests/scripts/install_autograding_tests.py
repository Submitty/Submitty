import sys
import os
import glob
import subprocess

# Path of this script (REPOSITORY_ROOT/tests/integrationTests/scripts/)
base_path = os.path.dirname(os.path.realpath(sys.argv[0]))

# Install the tests themselves to /var/local
subprocess.call(["rsync",
    os.path.join(base_path, "..", "tests"), # Source files
    "/var/local/hss/autograde_tests/", # Destination
    "-r", "--delete"]) # Copy recursively, delete extraneous files

# Fetch source relative to scripts directory, and install it to /var/local
subprocess.call(["rsync"] +
        glob.glob(os.path.join(base_path, "..", "..", "..", "grading", "*.cpp")) +
        ["/var/local/hss/autograde_tests/src", "-r", "--delete"])
