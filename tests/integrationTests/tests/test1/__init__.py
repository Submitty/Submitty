from lib import testcase

'''
@setup
def build_validator(test):
    test.compile_grading();
    test.link_validator()
'''

@testcase
def correct_json_output(test):
    test.run_validator()
    test.diff("test03_0_diff.json")
    test.diff("test03_1_diff.json")
    test.diff("submission.json")
