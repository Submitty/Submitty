# Necessary imports. Provides library functions to ease writing tests.
from lib import testcase

@testcase
def check_output(test):
    print "starting line highlight"
    test.build()
    test.run_run()
    test.diff("test01_output_correct.txt","data/output_instructor.txt")
    test.diff("test02_output_duplicates.txt","duplicate_lines.txt")
    test.diff("test03_output_extra.txt","extra_lines.txt")
    test.diff("test04_output_missing.txt","missing_lines.txt")

    #def check_grade(test):
    test.run_validator() 
    test.diff("submission.json")

