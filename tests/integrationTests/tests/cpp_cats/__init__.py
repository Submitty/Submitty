# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob


############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/sample_files/sample_assignment_config/cpp_cats"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/sample_files/sample_submissions/cpp_cats"

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
    subprocess.call(["cp",
        os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_input", "CatBreeds.txt"),
        os.path.join(test.testcase_path, "data")])
    subprocess.call(["cp",
        os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_output", "inst_output.txt"),
        os.path.join(test.testcase_path, "data")])

    subprocess.call(["cp"] +
            glob.glob(os.path.join(SAMPLE_SUBMISSIONS, "*.zip")) +
            [os.path.join(test.testcase_path, "data")])


############################################################################


@testcase
def allCorrect(test):
    subprocess.call(["unzip",
        "-q",  # quiet
        "-o",  # overwrite files
        os.path.join(test.testcase_path, "data/allCorrect.zip"),
        "-d",  # save to directory
        os.path.join(test.testcase_path, "data/")])

    test.run_compile()
    test.run_run()
    test.run_validator()

    test.diff("test03_output.txt","data/inst_output.txt")
    test.diff("test04_output.txt","data/inst_output.txt")
    test.diff("test05_output.txt","data/inst_output.txt")
    test.diff("test06_output.txt","data/inst_output.txt")

    test.empty_file("test02_STDOUT.txt")
    test.empty_file("test02_STDERR.txt")
    test.empty_file("test03_STDOUT.txt")
    test.empty_file("test03_STDERR.txt")
    test.empty_file("test04_STDOUT.txt")
    test.empty_file("test04_STDERR.txt")
    test.empty_file("test05_STDOUT.txt")
    test.empty_file("test05_STDERR.txt")
    test.empty_file("test06_STDOUT.txt")
    test.empty_file("test06_STDERR.txt")

    test.empty_file("test02_execute_logfile.txt")
    test.empty_file("test03_execute_logfile.txt")
    test.empty_file("test04_execute_logfile.txt")
    test.empty_file("test05_execute_logfile.txt")
    test.empty_file("test06_execute_logfile.txt")

    test.empty_json_diff("test03_0_diff.json")
    test.empty_json_diff("test04_0_diff.json")
    test.empty_json_diff("test05_0_diff.json")
    test.empty_json_diff("test06_0_diff.json")

    test.diff(".submit.grade",".submit.grade_allCorrect")
    test.diff("submission.json","submission.json_allCorrect")


@testcase
def columnSpacingOff(test):
    subprocess.call(["unzip",
        "-q",  # quiet
        "-o",  # overwrite files
        os.path.join(test.testcase_path, "data/columnSpacingOff.zip"),
        "-d",  # save to directory
        os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff(".submit.grade",".submit.grade_columnSpacingOff")
    test.diff("submission.json","submission.json_columnSpacingOff")


@testcase
def extraLinesAtEnd(test):
    subprocess.call(["unzip",
        "-q",  # quiet
        "-o",  # overwrite files
        os.path.join(test.testcase_path, "data/extraLinesAtEnd.zip"),
        "-d",  # save to directory
        os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff(".submit.grade",".submit.grade_extraLinesAtEnd")
    test.diff("submission.json","submission.json_extraLinesAtEnd")


@testcase
def extraSpacesAtEnd(test):
    subprocess.call(["unzip",
        "-q",  # quiet
        "-o",  # overwrite files
        os.path.join(test.testcase_path, "data/extraSpacesAtEnd.zip"),
        "-d",  # save to directory
        os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff(".submit.grade",".submit.grade_extraSpacesAtEnd")
    test.diff("submission.json","submission.json_extraSpacesAtEnd")


@testcase
def frontSpacingOff(test):
    subprocess.call(["unzip",
        "-q",  # quiet
        "-o",  # overwrite files
        os.path.join(test.testcase_path, "data/frontSpacingOff.zip"),
        "-d",  # save to directory
        os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff(".submit.grade",".submit.grade_frontSpacingOff")
    test.diff("submission.json","submission.json_frontSpacingOff")


@testcase
def lineOrderOff(test):
    subprocess.call(["unzip",
        "-q",  # quiet
        "-o",  # overwrite files
        os.path.join(test.testcase_path, "data/lineOrderOff.zip"),
        "-d",  # save to directory
        os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff(".submit.grade",".submit.grade_lineOrderOff")
    test.diff("submission.json","submission.json_lineOrderOff")


@testcase
def spacingOff(test):
    subprocess.call(["unzip",
        "-q",  # quiet
        "-o",  # overwrite files
        os.path.join(test.testcase_path, "data/spacingOff.zip"),
        "-d",  # save to directory
        os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff(".submit.grade",".submit.grade_spacingOff")
    test.diff("submission.json","submission.json_spacingOff")


@testcase
def spellingOff(test):
    subprocess.call(["unzip",
        "-q",  # quiet
        "-o",  # overwrite files
        os.path.join(test.testcase_path, "data/spellingOff.zip"),
        "-d",  # save to directory
        os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff(".submit.grade",".submit.grade_spellingOff")
    test.diff("submission.json","submission.json_spellingOff")
