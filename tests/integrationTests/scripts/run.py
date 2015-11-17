import sys
import os

import lib
sys.path.append("/var/local/hss/autograde_tests")
sys.path.append(os.path.dirname(os.path.realpath(sys.argv[0])))

import tests

if len(sys.argv) == 1:
    tests.run()
else:
    exec("tests." + sys.argv[1] + ".run()")
