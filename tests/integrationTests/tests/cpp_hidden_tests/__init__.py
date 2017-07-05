# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob


############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/cpp_hidden_tests/config"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/cpp_hidden_tests/submissions"

@prebuild
def initialize(test):
    try:
        os.mkdir(os.path.join(test.testcase_path, "assignment_config"))
    except OSError:
        pass
    try:
        os.mkdir(os.path.join(test.testcase_path, "data"))
    except OSError:
        pass

    subprocess.call(["cp",
                     os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "config.json"),
                     os.path.join(test.testcase_path, "assignment_config")])
    subprocess.call(["cp"] +
                    glob.glob(os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_output", "*.txt")) +
                    [os.path.join(test.testcase_path, "data")])


############################################################################


def cleanup(test):
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "*cpp")))
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "test*")))
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "results*")))



@testcase
def correct(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "frame.cpp"),
                     os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","correct_grade.txt","-b")
    test.json_diff("results.json","correct_results.json")

@testcase
def noerrorchecking(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "frame_noerrorchecking.cpp"),
                     os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","noerrorchecking_grade.txt","-b")
    test.json_diff("results.json","noerrorchecking_results.json")

@testcase
def hardcoded(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "frame_hardcoded.cpp"),
                     os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","hardcoded_grade.txt","-b")
    test.json_diff("results.json","hardcoded_results.json")

@testcase
def buggy(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "frame_buggy.cpp"),
                     os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","buggy_grade.txt","-b")
    test.json_diff("results.json","buggy_results.json")


# ---- SUBMISSIONS 1-3 ---- NO PENALTY ----
@testcase
def subnum_3(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "frame.cpp"),os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    test.run_validator("testuser","3","0")
    test.diff("grade.txt","correct_grade.txt","-b")
    test.json_diff("results.json","correct_results.json")


# ---- SUBMISSIONS 4-5 ---- 1 PT PENALTY ----
@testcase
def subnum_4(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "frame.cpp"),os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    test.run_validator("testuser","4","0")
    test.diff("grade.txt","correct_penalty1_grade.txt","-b")
    test.json_diff("results.json","correct_penalty1_results.json")

@testcase
def subnum_5(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "frame.cpp"),os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    test.run_validator("testuser","5","0")
    test.diff("grade.txt","correct_penalty1_grade.txt","-b")
    test.json_diff("results.json","correct_penalty1_results.json")


# ---- SUBMISSIONS 6-7 ---- 2 PT PENALTY ----
@testcase
def subnum_6(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "frame.cpp"),os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    test.run_validator("testuser","6","0")
    test.diff("grade.txt","correct_penalty2_grade.txt","-b")
    test.json_diff("results.json","correct_penalty2_results.json")

@testcase
def subnum_7(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "frame.cpp"),os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    test.run_validator("testuser","7","0")
    test.diff("grade.txt","correct_penalty2_grade.txt","-b")
    test.json_diff("results.json","correct_penalty2_results.json")


# ---- SUBMISSIONS 8-9 ---- 3 PT PENALTY ----
# ---- SUBMISSIONS 10-11 ---- 4 PT PENALTY ----


# ---- SUBMISSIONS 12 and above ---- 5 PT PENALTY ----
@testcase
def subnum_12(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "frame.cpp"),os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    test.run_validator("testuser","12","0")
    test.diff("grade.txt","correct_penalty5_grade.txt","-b")
    test.json_diff("results.json","correct_penalty5_results.json")

@testcase
def subnum_100(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "frame.cpp"),os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    test.run_validator("testuser","100","0")
    test.diff("grade.txt","correct_penalty5_grade.txt","-b")
    test.json_diff("results.json","correct_penalty5_results.json")



# ---- LOW SCORE WITH 5 PT PENALTY =>  NO NEGATIVE SCORE ----
@testcase
def subnum_buggy_100(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "frame_buggy.cpp"),os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    test.run_validator("testuser","100","0")
    test.diff("grade.txt","buggy_penalty5_grade.txt","-b")
    test.json_diff("results.json","buggy_penalty5_results.json")

 
