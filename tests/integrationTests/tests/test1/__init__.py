from lib import testcase

@testcase
def correct_json_output(test):
    test.build()
    test.run_validator()
    test.diff("test03_0_diff.json")
    test.diff("test03_1_diff.json")
    test.diff("submission.json")
