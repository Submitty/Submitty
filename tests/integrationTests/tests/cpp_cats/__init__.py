# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob
import shutil
import traceback

############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/cpp_cats/config"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/cpp_cats/submissions"

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
    subprocess.call(["cp",
        os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_input", "CatBreeds.txt"),
        os.path.join(test.testcase_path, "data")])

    subprocess.call(["cp"] +
            glob.glob(os.path.join(SAMPLE_SUBMISSIONS, "*.zip")) +
            [os.path.join(test.testcase_path, "data")])


############################################################################
def cleanup(test):
    subprocess.call(["rm"] + ["-rf"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "test*")))
    subprocess.call(["rm"] + ["-f"] +
                    glob.glob(os.path.join(test.testcase_path, "data", "results*")))

    os.mkdir(os.path.join(test.testcase_path, "data", "test_output"))
    subprocess.call(["cp",
        os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "test_output", "inst_output.txt"),
        os.path.join(test.testcase_path, "data", "test_output")])

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
def allCorrect(test):
    cleanup(test)
    subprocess.call(["unzip",
        "-q",  # quiet
        "-o",  # overwrite files
        os.path.join(test.testcase_path, "data/allCorrect.zip"),
        "-d",  # save to directory
        os.path.join(test.testcase_path, "data/")])

    test.run_compile()
    test.run_run()
    test.run_validator()

    test.diff("test03/output.txt","data/test_output/inst_output.txt")
    test.diff("test04/output.txt","data/test_output/inst_output.txt")
    test.diff("test05/output.txt","data/test_output/inst_output.txt")
    test.diff("test06/output.txt","data/test_output/inst_output.txt")

    test.empty_file("test02/STDOUT.txt")
    test.empty_file("test02/STDERR.txt")
    test.empty_file("test03/STDOUT.txt")
    test.empty_file("test03/STDERR.txt")
    test.empty_file("test04/STDOUT.txt")
    test.empty_file("test04/STDERR.txt")
    test.empty_file("test05/STDOUT.txt")
    test.empty_file("test05/STDERR.txt")
    test.empty_file("test06/STDOUT.txt")
    test.empty_file("test06/STDERR.txt")

    test.empty_file("test02/execute_logfile.txt")
    test.empty_file("test03/execute_logfile.txt")
    test.empty_file("test04/execute_logfile.txt")
    test.empty_file("test05/execute_logfile.txt")
    test.empty_file("test06/execute_logfile.txt")

    test.empty_json_diff("test03/0_diff.json")
    test.empty_json_diff("test04/0_diff.json")
    test.empty_json_diff("test05/0_diff.json")
    test.empty_json_diff("test06/0_diff.json")

    test.diff("grade.txt","grade.txt_allCorrect","-b")
    test.json_diff("results.json","results.json_allCorrect")


@testcase
def columnSpacingOff(test):
    cleanup(test)
    subprocess.call(["unzip",
        "-q",  # quiet
        "-o",  # overwrite files
        os.path.join(test.testcase_path, "data/columnSpacingOff.zip"),
        "-d",  # save to directory
        os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","grade.txt_columnSpacingOff","-b")
    test.json_diff("results.json","results.json_columnSpacingOff")


@testcase
def extraLinesAtEnd(test):
    cleanup(test)
    subprocess.call(["unzip",
        "-q",  # quiet
        "-o",  # overwrite files
        os.path.join(test.testcase_path, "data/extraLinesAtEnd.zip"),
        "-d",  # save to directory
        os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","grade.txt_extraLinesAtEnd","-b")
    test.json_diff("results.json","results.json_extraLinesAtEnd")


@testcase
def extraSpacesAtEnd(test):
    cleanup(test)
    subprocess.call(["unzip",
        "-q",  # quiet
        "-o",  # overwrite files
        os.path.join(test.testcase_path, "data/extraSpacesAtEnd.zip"),
        "-d",  # save to directory
        os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","grade.txt_extraSpacesAtEnd","-b")
    test.json_diff("results.json","results.json_extraSpacesAtEnd")


@testcase
def frontSpacingOff(test):
    cleanup(test)
    subprocess.call(["unzip",
        "-q",  # quiet
        "-o",  # overwrite files
        os.path.join(test.testcase_path, "data/frontSpacingOff.zip"),
        "-d",  # save to directory
        os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","grade.txt_frontSpacingOff","-b")
    test.json_diff("results.json","results.json_frontSpacingOff")


@testcase
def lineOrderOff(test):
    cleanup(test)
    subprocess.call(["unzip",
        "-q",  # quiet
        "-o",  # overwrite files
        os.path.join(test.testcase_path, "data/lineOrderOff.zip"),
        "-d",  # save to directory
        os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","grade.txt_lineOrderOff","-b")
    test.json_diff("results.json","results.json_lineOrderOff")


@testcase
def spacingOff(test):
    cleanup(test)
    subprocess.call(["unzip",
        "-q",  # quiet
        "-o",  # overwrite files
        os.path.join(test.testcase_path, "data/spacingOff.zip"),
        "-d",  # save to directory
        os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","grade.txt_spacingOff","-b")
    test.json_diff("results.json","results.json_spacingOff")


@testcase
def spellingOff(test):
    cleanup(test)
    subprocess.call(["unzip",
        "-q",  # quiet
        "-o",  # overwrite files
        os.path.join(test.testcase_path, "data/spellingOff.zip"),
        "-d",  # save to directory
        os.path.join(test.testcase_path, "data/")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","grade.txt_spellingOff","-b")
    test.json_diff("results.json","results.json_spellingOff")
