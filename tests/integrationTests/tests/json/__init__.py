# Necessary imports. Provides library functions to ease writing tests.
from lib import testcase

# Within each test, there should be a single function decorated with @setup.
# This function is guaranteed to be run before all other test functions.
#@setup
#def build_validator(test): # Function names are arbitary
#    test.build();

@testcase
def correct_json_output(test):
    print "inside json test case"

    test.build();
    test.run_validator() # Runs validator.out with some sane arguments

    # Check differences on output files. Files within the data directory are compared with
    # their counterparts in the validation directory.
    test.diff("test01_0_diff.json")
    test.diff("submission.json")
