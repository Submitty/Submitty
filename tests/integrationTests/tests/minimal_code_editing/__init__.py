# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob


############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/minimal_code_editing/config"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/minimal_code_editing/submissions"

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
                    glob.glob(os.path.join(test.testcase_path, "data", "*cpp")))
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "test*")))
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "results*")))




@testcase
def add_delete_lines(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "add_delete_lines.cpp"),
                     os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "fancy_hello_world.cpp"),
                     os.path.join(test.testcase_path, "data")])
    test.run_validator()
    test.diff("grade.txt","add_delete_lines_grade.txt","-b")
    test.json_diff("results.json","add_delete_lines_results.json")
    test.json_diff("test01_0_diff.json","add_delete_lines_test01_0_diff.json")


@testcase
def curly_brace_placement(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "curly_brace_placement.cpp"),
                     os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "fancy_hello_world.cpp"),
                     os.path.join(test.testcase_path, "data")])
    test.run_validator()
    test.diff("grade.txt","curly_brace_placement_grade.txt","-b")
    test.json_diff("results.json","curly_brace_placement_results.json")
    test.json_diff("test01_0_diff.json","curly_brace_placement_test01_0_diff.json")


@testcase
def edits_neighboring_lines(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "edits_neighboring_lines.cpp"),
                     os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "fancy_hello_world.cpp"),
                     os.path.join(test.testcase_path, "data")])
    test.run_validator()
    test.diff("grade.txt","edits_neighboring_lines_grade.txt","-b")
    test.json_diff("results.json","edits_neighboring_lines_results.json")
    test.json_diff("test01_0_diff.json","edits_neighboring_lines_test01_0_diff.json")


@testcase
def extra_spaces(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "extra_spaces.cpp"),
                     os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "fancy_hello_world.cpp"),
                     os.path.join(test.testcase_path, "data")])
    test.run_validator()
    test.diff("grade.txt","extra_spaces_grade.txt","-b")
    test.json_diff("results.json","extra_spaces_results.json")
    test.json_diff("test01_0_diff.json","extra_spaces_test01_0_diff.json")


@testcase
def fancy_hello_world(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "fancy_hello_world.cpp"),
                     os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "fancy_hello_world.cpp"),
                     os.path.join(test.testcase_path, "data")])
    test.run_validator()
    test.diff("grade.txt","fancy_hello_world_grade.txt","-b")
    test.json_diff("results.json","fancy_hello_world_results.json")
    test.json_diff("test01_0_diff.json","fancy_hello_world_test01_0_diff.json")


@testcase
def four_space_indent(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "four_space_indent.cpp"),
                     os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "fancy_hello_world.cpp"),
                     os.path.join(test.testcase_path, "data")])
    test.run_validator()
    test.diff("grade.txt","four_space_indent_grade.txt","-b")
    test.json_diff("results.json","four_space_indent_results.json")
    test.json_diff("test01_0_diff.json","four_space_indent_test01_0_diff.json")


@testcase
def noise(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "noise.cpp"),
                     os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "fancy_hello_world.cpp"),
                     os.path.join(test.testcase_path, "data")])
    test.run_validator()
    test.diff("grade.txt","noise_grade.txt","-b")
    test.json_diff("results.json","noise_results.json")
    test.json_diff("test01_0_diff.json","noise_test01_0_diff.json")


@testcase
def tabs(test):
    cleanup(test)
    subprocess.call(["cp",os.path.join(SAMPLE_SUBMISSIONS, "tabs.cpp"),
                     os.path.join(test.testcase_path, "data")])
    test.run_compile()
    test.run_run()
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "fancy_hello_world.cpp"),
                     os.path.join(test.testcase_path, "data")])
    test.run_validator()
    test.diff("grade.txt","tabs_grade.txt","-b")
    test.json_diff("results.json","tabs_results.json")
    test.json_diff("test01_0_diff.json","tabs_test01_0_diff.json")

