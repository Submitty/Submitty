# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_TUTORIAL_DIR

import subprocess
import os
import glob
import shutil
import traceback

############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_TUTORIAL_DIR + "/examples/12_system_calls/config"
SAMPLE_SUBMISSIONS = SUBMITTY_TUTORIAL_DIR + "/examples/12_system_calls/submissions/"

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

    subprocess.call(["cp",
                     os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "config.json"),
                     os.path.join(test.testcase_path, "assignment_config")])


############################################################################


def cleanup(test):
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "*c")))
    subprocess.call(["rm"] + ["-rf"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "test*")))
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "results*")))



@testcase
def schema_validation(test):
    cleanup(test)
    config_path = os.path.join(test.testcase_path, 'assignment_config', 'complete_config.json')
    try:
        test.validate_complete_config(config_path)
    except Exception:
        traceback.print_exc()
        raise

@testcase
def no_fork(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "no_fork.c"),
                     os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","no_fork_grade.txt","-b")
    test.json_diff("results.json","no_fork_results.json")
    test.diff("test02/STDOUT.txt","no_fork_STDOUT.txt")
    test.diff("test03/STDOUT.txt","no_fork_STDOUT.txt")
    test.empty_file("test02/STDERR.txt")
    test.empty_file("test03/STDERR.txt")
    test.empty_file("test02/execute_logfile.txt")
    test.empty_file("test03/execute_logfile.txt")


@testcase
def serial_fork(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "serial_fork.c"),
                     os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","serial_fork_grade.txt","-b")
    test.json_diff("results.json","serial_fork_results.json")
    #test.diff("test02/STDOUT.txt","serial_fork_10_STDOUT.txt")
    #test.diff("test03/STDOUT.txt","serial_fork_30_STDOUT.txt")
    test.empty_file("test02/STDERR.txt")
    test.empty_file("test03/STDERR.txt")
    test.empty_file("test02/execute_logfile.txt")
    test.empty_file("test03/execute_logfile.txt")


@testcase
def parallel_fork(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "parallel_fork.c"),
                     os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","parallel_fork_grade.txt","-b")
    test.json_diff("results.json","parallel_fork_results.json")
    #test.diff("test02/STDOUT.txt","parallel_fork_10_STDOUT.txt")
    #test.diff("test03/STDOUT.txt","parallel_fork_30_STDOUT.txt")
    test.empty_file("test02/STDERR.txt")
    test.empty_file("test03/STDERR.txt")
    test.empty_file("test02/execute_logfile.txt")
    test.empty_file("test03/execute_logfile.txt")


@testcase
def tree_fork(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "tree_fork.c"),
                     os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","tree_fork_grade.txt","-b")
    test.json_diff("results.json","tree_fork_results.json")
    #test.diff("test02/STDOUT.txt","tree_fork_10_STDOUT.txt")
    #test.diff("test03/STDOUT.txt","tree_fork_30_STDOUT.txt")
    test.empty_file("test02/STDERR.txt")
    test.empty_file("test03/STDERR.txt")
    test.empty_file("test02/execute_logfile.txt")
    test.empty_file("test03/execute_logfile.txt")


#@testcase
#def fork_bomb_print(test):
#    cleanup(test)
#    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "fork_bomb_print.c"),
#                     os.path.join(test.testcase_path, "data")])
#    test.run_compile()
#    test.run_run()
#    test.run_validator()
#    test.diff("grade.txt","fork_bomb_print_grade.txt","-b")
#    test.json_diff("results.json","fork_bomb_print_results.json")
#    #test.diff("test02/STDOUT.txt","fork_bomb_print_10_STDOUT.txt")
#    #test.diff("test03/STDOUT.txt","fork_bomb_print_30_STDOUT.txt")
#    test.empty_file("test02/STDERR.txt")
#    test.empty_file("test03/STDERR.txt")
#    test.empty_file("test02/execute_logfile.txt")
#    test.empty_file("test03/execute_logfile.txt")


