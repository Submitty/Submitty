# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob
import shutil


############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/python_custom_validation/config"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/python_custom_validation/submissions"

@prebuild
def initialize(test):
    try:
        os.mkdir(os.path.join(test.testcase_path, "assignment_config"))
    except OSError:
        pass
    try:
        data_path = os.path.join(test.testcase_path, "data")
        if os.path.isdir(data_path):
            shutil.rmtree(data_path)
        os.mkdir(data_path)
    except OSError:
        pass
    try:
        os.mkdir(os.path.join(test.testcase_path, "build"))
    except OSError:
        pass
    try:
        os.mkdir(os.path.join(test.testcase_path, "build/custom_validation_code"))
    except OSError:
        pass
    subprocess.call(["cp",
                     os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "config.json"),
                     os.path.join(test.testcase_path, "assignment_config")])
    subprocess.call(["cp",
                     os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "custom_validation_code", "grader.py"),
                     os.path.join(test.testcase_path, "build/custom_validation_code")])
    subprocess.call(["cp",
                     os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "custom_validation_code", "OLD_grader.py"),
                     os.path.join(test.testcase_path, "build/custom_validation_code")])


############################################################################
def cleanup(test):
    subprocess.call(["rm"] + ["-rf"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "test*")))
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "results*")))

@testcase
def schema_validation(test):
    cleanup(test)
    config_path = os.path.join(test.testcase_path, 'assignment_config', 'complete_config.json')
    test.validate_complete_config(config_path)

@testcase
def correct(test):
    cleanup(test)
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data/", "*.cpp")))
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "correct.cpp"),
                     os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    subprocess.call(["cp",
                     os.path.join(test.testcase_path, "build","custom_validation_code","grader.py"),
                     os.path.join(test.testcase_path, "data/")])
    subprocess.call(["cp",
                     os.path.join(test.testcase_path, "build","custom_validation_code","OLD_grader.py"),
                     os.path.join(test.testcase_path, "data/")])
    test.run_validator()

    test.diff("validation_stderr_2_0.txt","validation_stderr_2_0.txt_correct","-b")
    test.diff("validation_stderr_3_0.txt","validation_stderr_3_0.txt_correct","-b")
    test.diff("validation_stderr_4_0.txt","validation_stderr_4_0.txt_correct","-b")

    test.json_diff("validation_results_2_0.json","validation_results_2_0.json_correct")
    test.json_diff("validation_results_3_0.json","validation_results_3_0.json_correct")
    test.json_diff("validation_results_4_0.json","validation_results_4_0.json_correct")

    test.diff("validation_logfile_3_0.txt","validation_logfile_3_0.txt_correct","-b")
    test.diff("validation_logfile_4_0.txt","validation_logfile_4_0.txt_correct","-b")

    test.diff("grade.txt","grade.txt_correct","-b")
    test.json_diff("results.json","results.json_correct")

@testcase
def missing_label(test):
    cleanup(test)
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data/", "*.cpp")))
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "missing_label.cpp"),
                     os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    subprocess.call(["cp",
                 os.path.join(test.testcase_path, "build","custom_validation_code","grader.py"),
                 os.path.join(test.testcase_path, "data/")])
    subprocess.call(["cp",
                     os.path.join(test.testcase_path, "build","custom_validation_code","OLD_grader.py"),
                     os.path.join(test.testcase_path, "data/")])
    test.run_validator()
    test.diff("grade.txt","grade.txt_missing_label","-b")
    test.json_diff("results.json","results.json_missing_label")

    test.diff("validation_stderr_2_0.txt","validation_stderr_2_0.txt_missing_label","-b")
    test.diff("validation_stderr_3_0.txt","validation_stderr_3_0.txt_missing_label","-b")
    test.diff("validation_stderr_4_0.txt","validation_stderr_4_0.txt_missing_label","-b")

    test.json_diff("validation_results_2_0.json","validation_results_2_0.json_missing_label")
    test.json_diff("validation_results_3_0.json","validation_results_3_0.json_missing_label")
    test.json_diff("validation_results_4_0.json","validation_results_4_0.json_missing_label")

    test.diff("validation_logfile_3_0.txt","validation_logfile_3_0.txt_missing_label","-b")
    test.diff("validation_logfile_4_0.txt","validation_logfile_4_0.txt_missing_label","-b")


@testcase
def wrong_num(test):
    cleanup(test)
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data/", "*.cpp")))
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "wrong_num.cpp"),
                     os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    subprocess.call(["cp",
                     os.path.join(test.testcase_path, "build","custom_validation_code","grader.py"),
                     os.path.join(test.testcase_path, "data/")])
    subprocess.call(["cp",
                     os.path.join(test.testcase_path, "build","custom_validation_code","OLD_grader.py"),
                     os.path.join(test.testcase_path, "data/")])
    test.run_validator()
    test.diff("grade.txt","grade.txt_wrong_num","-b")
    test.json_diff("results.json","results.json_wrong_num")

    test.diff("validation_stderr_2_0.txt","validation_stderr_2_0.txt_wrong_num","-b")
    test.diff("validation_stderr_3_0.txt","validation_stderr_3_0.txt_wrong_num","-b")
    test.diff("validation_stderr_4_0.txt","validation_stderr_4_0.txt_wrong_num","-b")

    test.json_diff("validation_results_2_0.json","validation_results_2_0.json_wrong_num")
    test.json_diff("validation_results_3_0.json","validation_results_3_0.json_wrong_num")
    test.json_diff("validation_results_4_0.json","validation_results_4_0.json_wrong_num")

    test.diff("validation_logfile_3_0.txt","validation_logfile_3_0.txt_wrong_num","-b")
    test.diff("validation_logfile_4_0.txt","validation_logfile_4_0.txt_wrong_num","-b")


@testcase
def wrong_total(test):
    cleanup(test)
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data/", "*.cpp")))
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "wrong_total.cpp"),
                     os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    subprocess.call(["cp",
                     os.path.join(test.testcase_path, "build","custom_validation_code","grader.py"),
                     os.path.join(test.testcase_path, "data/")])
    subprocess.call(["cp",
                     os.path.join(test.testcase_path, "build","custom_validation_code","OLD_grader.py"),
                     os.path.join(test.testcase_path, "data/")])
    test.run_validator()
    test.diff("grade.txt","grade.txt_wrong_total","-b")
    test.json_diff("results.json","results.json_wrong_total")

    test.diff("validation_stderr_2_0.txt","validation_stderr_2_0.txt_wrong_total","-b")
    test.diff("validation_stderr_3_0.txt","validation_stderr_3_0.txt_wrong_total","-b")
    test.diff("validation_stderr_4_0.txt","validation_stderr_4_0.txt_wrong_total","-b")

    test.json_diff("validation_results_2_0.json","validation_results_2_0.json_wrong_total")
    test.json_diff("validation_results_3_0.json","validation_results_3_0.json_wrong_total")
    test.json_diff("validation_results_4_0.json","validation_results_4_0.json_wrong_total")

    test.diff("validation_logfile_3_0.txt","validation_logfile_3_0.txt_wrong_total","-b")
    test.diff("validation_logfile_4_0.txt","validation_logfile_4_0.txt_wrong_total","-b")

@testcase
def not_random(test):
    cleanup(test)
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data/", "*.cpp")))
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "not_random.cpp"),
                     os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    subprocess.call(["cp",
                     os.path.join(test.testcase_path, "build","custom_validation_code","grader.py"),
                     os.path.join(test.testcase_path, "data/")])
    subprocess.call(["cp",
                     os.path.join(test.testcase_path, "build","custom_validation_code","OLD_grader.py"),
                     os.path.join(test.testcase_path, "data/")])
    test.run_validator()
    test.diff("grade.txt","grade.txt_not_random","-b")
    test.json_diff("results.json","results.json_not_random")

    test.diff("validation_stderr_2_0.txt","validation_stderr_2_0.txt_not_random","-b")
    test.diff("validation_stderr_3_0.txt","validation_stderr_3_0.txt_not_random","-b")
    test.diff("validation_stderr_4_0.txt","validation_stderr_4_0.txt_not_random","-b")

    test.json_diff("validation_results_2_0.json","validation_results_2_0.json_not_random")
    test.json_diff("validation_results_3_0.json","validation_results_3_0.json_not_random")
    test.json_diff("validation_results_4_0.json","validation_results_4_0.json_not_random")

    test.diff("validation_logfile_3_0.txt","validation_logfile_3_0.txt_not_random","-b")
    test.diff("validation_logfile_4_0.txt","validation_logfile_4_0.txt_not_random","-b")

@testcase
def all_bugs(test):
    cleanup(test)
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data/", "*.cpp")))
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "all_bugs.cpp"),
                     os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    subprocess.call(["cp",
                     os.path.join(test.testcase_path, "build","custom_validation_code","grader.py"),
                     os.path.join(test.testcase_path, "data/")])
    subprocess.call(["cp",
                     os.path.join(test.testcase_path, "build","custom_validation_code","OLD_grader.py"),
                     os.path.join(test.testcase_path, "data/")])
    test.run_validator()
    test.diff("grade.txt","grade.txt_all_bugs","-b")
    test.json_diff("results.json","results.json_all_bugs")

    test.diff("validation_stderr_2_0.txt","validation_stderr_2_0.txt_all_bugs","-b")
    test.diff("validation_stderr_3_0.txt","validation_stderr_3_0.txt_all_bugs","-b")
    test.diff("validation_stderr_4_0.txt","validation_stderr_4_0.txt_all_bugs","-b")

    test.diff("validation_logfile_3_0.txt","validation_logfile_3_0.txt_all_bugs","-b")
    test.diff("validation_logfile_4_0.txt","validation_logfile_4_0.txt_all_bugs","-b")

    test.json_diff("validation_results_2_0.json","validation_results_2_0.json_all_bugs")
    test.json_diff("validation_results_3_0.json","validation_results_3_0.json_all_bugs")
    test.json_diff("validation_results_4_0.json","validation_results_4_0.json_all_bugs")


