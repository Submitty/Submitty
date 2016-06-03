# Necessary imports. Provides library functions to ease writing tests.
from lib import testcase

@testcase
def correct_json_output(test):
    test.build()
    test.run_run()
    test.run_validator() # Runs validator.out with some sane arguments
    # Check differences on output files. Files within the data directory are compared with
    # their counterparts in the validation directory.

#    test.diff("submission.json")
    test.diff("duplicate_lines.json")
