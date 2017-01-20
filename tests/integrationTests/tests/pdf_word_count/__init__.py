# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob


############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/sample_files/sample_assignment_config/pdf_word_count"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/sample_files/sample_submissions/pdf_word_count"

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


############################################################################


def cleanup(test):
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "*pdf")))
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "test*")))
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "results*")))



@testcase
def too_few(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "words_249.pdf"),
                     os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("results_grade.txt","too_few_results_grade.txt","-b")
    test.json_diff("results.json","too_few_results.json")
    test.empty_file("test02_STDOUT_0.txt")
    test.empty_file("test02_STDERR_0.txt")
    test.diff("test02_STDOUT_1.txt","too_few_test02_STDOUT_1.txt")
    test.empty_file("test02_STDERR_1.txt")

@testcase
def too_many(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "words_1463.pdf"),
                     os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("results_grade.txt","too_many_results_grade.txt","-b")
    test.json_diff("results.json","too_many_results.json")
    test.empty_file("test02_STDOUT_0.txt")
    test.empty_file("test02_STDERR_0.txt")
    test.diff("test02_STDOUT_1.txt","too_many_test02_STDOUT_1.txt")
    test.empty_file("test02_STDERR_1.txt")

@testcase
def just_right(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "words_881.pdf"),
                     os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("results_grade.txt","just_right_results_grade.txt","-b")
    test.json_diff("results.json","just_right_results.json")
    test.empty_file("test02_STDOUT_0.txt")
    test.empty_file("test02_STDERR_0.txt")
    test.diff("test02_STDOUT_1.txt","just_right_test02_STDOUT_1.txt")
    test.empty_file("test02_STDERR_1.txt")



