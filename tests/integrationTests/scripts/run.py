#!/usr/bin/python2

import sys
import os
import glob
import subprocess

import lib

import tests

# If we were given an argument, run only the test with that name.
# Otherwise, run every test.
if len(sys.argv) == 1:
    lib.run_all()
else:
    lib.run_test(sys.argv[1])
