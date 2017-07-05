# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_TUTORIAL_DIR

import subprocess
import os
import glob


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
        os.mkdir(os.path.join(test.testcase_path, "data"))
    except OSError:
        pass

    subprocess.call(["cp",
                     os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "config.json"),
                     os.path.join(test.testcase_path, "assignment_config")])


############################################################################


def cleanup(test):
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "*c")))
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "test*")))
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "results*")))



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
    test.diff("test02_STDOUT.txt","no_fork_STDOUT.txt")
    test.diff("test03_STDOUT.txt","no_fork_STDOUT.txt")
    test.empty_file("test02_STDERR.txt")
    test.empty_file("test03_STDERR.txt")
    test.empty_file("test02_execute_logfile.txt")
    test.empty_file("test03_execute_logfile.txt")


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
    #test.diff("test02_STDOUT.txt","serial_fork_10_STDOUT.txt")
    #test.diff("test03_STDOUT.txt","serial_fork_30_STDOUT.txt")
    test.empty_file("test02_STDERR.txt")
    test.empty_file("test03_STDERR.txt")
    test.empty_file("test02_execute_logfile.txt")
    test.empty_file("test03_execute_logfile.txt")


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
    #test.diff("test02_STDOUT.txt","parallel_fork_10_STDOUT.txt")
    #test.diff("test03_STDOUT.txt","parallel_fork_30_STDOUT.txt")
    test.empty_file("test02_STDERR.txt")
    test.empty_file("test03_STDERR.txt")
    test.empty_file("test02_execute_logfile.txt")
    test.empty_file("test03_execute_logfile.txt")


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
    #test.diff("test02_STDOUT.txt","tree_fork_10_STDOUT.txt")
    #test.diff("test03_STDOUT.txt","tree_fork_30_STDOUT.txt")
    test.empty_file("test02_STDERR.txt")
    test.empty_file("test03_STDERR.txt")
    test.empty_file("test02_execute_logfile.txt")
    test.empty_file("test03_execute_logfile.txt")


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
#    #test.diff("test02_STDOUT.txt","fork_bomb_print_10_STDOUT.txt")
#    #test.diff("test03_STDOUT.txt","fork_bomb_print_30_STDOUT.txt")
#    test.empty_file("test02_STDERR.txt")
#    test.empty_file("test03_STDERR.txt")
#    test.empty_file("test02_execute_logfile.txt")
#    test.empty_file("test03_execute_logfile.txt")


