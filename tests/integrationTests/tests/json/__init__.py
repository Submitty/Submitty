# Necessary imports. Provides library functions to ease writing tests.
from lib import testcase

@testcase
def correct_json_output(test):
    test.run_validator() # Runs validator.out with default arguments
    # Check differences on output files. Files within the data directory are compared with
    # their counterparts in the validation directory.
    test.json_diff("test02_0_diff.json")
    test.json_diff("results.json")
