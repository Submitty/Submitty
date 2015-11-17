import sys
import os
import glob
import subprocess

base_path = os.path.dirname(os.path.realpath(sys.argv[0]))

subprocess.call(["rsync", os.path.join(base_path, "..", "tests"), "/var/local/hss/autograde_tests/", "-r", "--delete"])
subprocess.call(["rsync"] + glob.glob(os.path.join(base_path, "..", "..", "..", "grading", "*.cpp"))
        + ["/var/local/hss/autograde_tests/src", "-r", "--delete"])
