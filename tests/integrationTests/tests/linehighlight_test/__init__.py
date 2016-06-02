# Necessary imports. Provides library functions to ease writing tests.
from lib import testcase

'''
# Within each test, there should be a single function decorated with @setup.
# This function is guaranteed to be run before all other test functions.
@setup
def build_validator(test): # Function names are arbitary
    #test.use_cmake(); # Compiles grading code, including the header assignment_config/config.h
    test.compile_grading(); # Compiles grading code, including the header assignment_config/config.h
    test.link_validator() # Links the executable validator.out
    test.link_runner()
'''

@testcase
def correct_json_output(test):
    test.run_runner()
    test.run_validator() # Runs validator.out with some sane arguments
    # Check differences on output files. Files within the data directory are compared with
    # their counterparts in the validation directory.
    test.diff("submission.json")
    #test.diff("duplicate_lines.json")
