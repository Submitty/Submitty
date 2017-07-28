# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob

############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/python_multipart_static_analysis/config"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/python_multipart_static_analysis/submissions"

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
    for i in [str(n) for n in range(1, 5)]:
        try:
            os.mkdir(os.path.join(test.testcase_path, "data", "part" + i))
        except OSError:
            pass
        subprocess.call(["cp",
            os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_output", "p" + i + "_out.txt"),
            os.path.join(test.testcase_path, "data")])


def cleanup(test):
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data", "part*", "*")))
    subprocess.call(["rm"] + ["-f"] +
            glob.glob(os.path.join(test.testcase_path, "data", "test*")))


@testcase
def correct(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "p1_sol.py"),
                     os.path.join(test.testcase_path, "data", "part1")])
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "p2_sol.txt"),
                     os.path.join(test.testcase_path, "data", "part2")])
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "p3_sol.py"),
                     os.path.join(test.testcase_path, "data", "part3")])
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "p4_sol.py"),
                     os.path.join(test.testcase_path, "data", "part4")])
    test.run_compile()  # NOTE: This is necessary to rename part2 file
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_correct", "-b")
    test.json_diff("results.json", "results.json_correct")



@testcase
def buggy(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "p1_bug.py"),
                     os.path.join(test.testcase_path, "data", "part1")])
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "p2_bug.txt"),
                     os.path.join(test.testcase_path, "data", "part2")])
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "p3_bug.py"),
                     os.path.join(test.testcase_path, "data", "part3")])
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "p4_bug.py"),
                     os.path.join(test.testcase_path, "data", "part4")])
    test.run_compile()  # NOTE: This is necessary to rename part2 file
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_buggy", "-b")
    test.json_diff("results.json", "results.json_buggy")


@testcase
def buggy2(test):
    cleanup(test)

    # FIXME: INFINITE LOOP(?) WHEN THIS DOES NOT EXIST
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "p1_bug.py"),
                     os.path.join(test.testcase_path, "data", "part1")])

    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "p2_bug2.txt"),
                     os.path.join(test.testcase_path, "data", "part2")])
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "p3_bug2.py"),
                     os.path.join(test.testcase_path, "data", "part3")])

    # FIXME: INFINITE LOOP(?) WHEN THIS DOES NOT EXIST
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "p4_bug.py"),
                     os.path.join(test.testcase_path, "data", "part4")])
    test.run_compile()  # NOTE: This is necessary to rename part2 file
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_buggy2", "-b")
    test.json_diff("results.json", "results.json_buggy2")


@testcase
def buggy3(test):
    cleanup(test)

    # FIXME: INFINITE LOOP(?) WHEN THIS DOES NOT EXIST
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "p1_bug.py"),
                     os.path.join(test.testcase_path, "data", "part1")])

    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "p2_bug3.txt"),
                     os.path.join(test.testcase_path, "data", "part2")])
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "p3_bug3.py"),
                     os.path.join(test.testcase_path, "data", "part3")])

    # FIXME: INFINITE LOOP(?) WHEN THIS DOES NOT EXIST
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "p4_bug.py"),
                     os.path.join(test.testcase_path, "data", "part4")])
    test.run_compile()  # NOTE: This is necessary to rename part2 file
    test.run_run()
    test.run_validator()
    test.diff("grade.txt", "grade.txt_buggy3", "-b")
    test.json_diff("results.json", "results.json_buggy3")
