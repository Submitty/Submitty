from lib import testcase

@testcase
def compile(test):
    test.compile_grading();
    test.link_validator()

@testcase
def run(test):
    test.run_validator()

@testcase
def diff(test):
    test.diff("test03_0_diff.json")
    test.diff("test03_1_diff.json")
    test.diff("submission.json")
