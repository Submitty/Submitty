#!/usr/bin/python3
import sys
import subprocess
import os.path
import glob

nArgs = len(sys.argv)
if (nArgs < 2):
    raise Exception("Need at least one file")
files = []
for i in range(1, nArgs):
    path = os.path.join(os.getcwd(), sys.argv[i])
    files.extend(glob.glob(path))
if (not len(files)):
    raise Exception("No matching files found for the input pattern")
command = ["cloc", "--csv", "--quiet"]
command.extend(files)
result = subprocess.check_output(command).splitlines()
if (len(result)):
    # last line of the output contains the comment lines count
    out = (result[-1].decode("utf-8")).split(",")
    print(out[3])
else:
    print(0)
