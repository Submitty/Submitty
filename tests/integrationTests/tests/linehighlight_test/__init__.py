# Necessary imports. Provides library functions to ease writing tests.
from lib import testcase

import subprocess
import os


############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES


# FIXME:  THESE GLOBAL VARIABLE PATHS SHOULD BE SET ELSEWHERE (?)

SAMPLE_ASSIGNMENT_CONFIG = "/usr/local/hss/sample_files/sample_assignment_config/python_buggy_output"
SAMPLE_SUBMISSIONS       = "/usr/local/hss/sample_files/sample_submissions/python_buggy_output"

this_directory = os.path.dirname(os.path.realpath(__file__))

# FIXME:  MAYBE THESE STATEMENTS SHOULD BE WRAPPED UP IN A FUNCTION?

subprocess.call(["mkdir", "-p", this_directory+"/assignment_config"])
subprocess.call(["mkdir", "-p", this_directory+"/data"])

subprocess.call(["cp", SAMPLE_ASSIGNMENT_CONFIG+"/config.h", this_directory+"/assignment_config/"])
subprocess.call(["cp", SAMPLE_ASSIGNMENT_CONFIG+"/test_input/gettysburg_address.txt", this_directory+"/data/"])
subprocess.call(["cp", SAMPLE_ASSIGNMENT_CONFIG+"/test_output/output_instructor.txt", this_directory+"/data/"])
#doing this so the wildcard expansion works
os.system("cp "+SAMPLE_SUBMISSIONS+"/*py "+this_directory+"/data/")



############################################################################


@testcase
def check_output(test):
    print "starting line highlight"
    test.build()
    test.run_run()
    test.diff("test01_output_correct.txt","data/output_instructor.txt")
    test.diff("test02_output_duplicates.txt","duplicate_lines.txt")
    test.diff("test03_output_duplicates.txt","duplicate_lines.txt")
    test.diff("test04_output_extra.txt","extra_lines.txt")
    test.diff("test05_output_extra.txt","extra_lines.txt")
    test.diff("test06_output_missing.txt","missing_lines.txt")
    test.diff("test07_output_missing.txt","missing_lines.txt")
    test.diff("test08_output_reordered.txt","output_reordered.txt")
    test.diff("test09_output_reordered.txt","output_reordered.txt")

@testcase
def check_json(test):
    test.run_validator() 
    test.json_diff("test01_0_diff.json")
    test.json_diff("test02_0_diff.json")
    test.json_diff("test03_0_diff.json")
    test.json_diff("test04_0_diff.json")
    test.json_diff("test05_0_diff.json")
    test.json_diff("test06_0_diff.json")
    test.json_diff("test07_0_diff.json")
    test.json_diff("test08_0_diff.json")
    test.json_diff("test09_0_diff.json")

@testcase
def check_grade(test):
    test.diff("submission.json")

@testcase
def check_empty(test):
    test.empty_file("test01_STDERR.txt")
    test.empty_file("test01_STDOUT.txt")
    test.empty_file("test02_STDERR.txt")
    test.empty_file("test02_STDOUT.txt")
    test.empty_file("test03_STDERR.txt")
    test.empty_file("test03_STDOUT.txt")
    test.empty_file("test04_STDERR.txt")
    test.empty_file("test04_STDOUT.txt")
    test.empty_file("test05_STDERR.txt")
    test.empty_file("test05_STDOUT.txt")
    test.empty_file("test06_STDERR.txt")
    test.empty_file("test06_STDOUT.txt")
    test.empty_file("test07_STDERR.txt")
    test.empty_file("test07_STDOUT.txt")
    test.empty_file("test08_STDERR.txt")
    test.empty_file("test08_STDOUT.txt")
    test.empty_file("test09_STDERR.txt")
    test.empty_file("test09_STDOUT.txt")

    test.empty_file("test01_execute_logfile.txt")
    test.empty_file("test02_execute_logfile.txt")
    test.empty_file("test03_execute_logfile.txt")
    test.empty_file("test04_execute_logfile.txt")
    test.empty_file("test05_execute_logfile.txt")
    test.empty_file("test06_execute_logfile.txt")
    test.empty_file("test07_execute_logfile.txt")
    test.empty_file("test08_execute_logfile.txt")
    test.empty_file("test09_execute_logfile.txt")
