# Necessary imports. Provides library functions to ease writing tests.
from lib import prebuild, testcase, SUBMITTY_INSTALL_DIR

import subprocess
import os
import glob
import shutil
import traceback

############################################################################
# COPY THE ASSIGNMENT FROM THE SAMPLE ASSIGNMENTS DIRECTORIES

SAMPLE_ASSIGNMENT_CONFIG = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/upload_word_docx/config"
SAMPLE_SUBMISSIONS       = SUBMITTY_INSTALL_DIR + "/more_autograding_examples/upload_word_docx/submissions"

@prebuild
def initialize(test):
    try:
        os.mkdir(os.path.join(test.testcase_path, "assignment_config"))
    except OSError:
        pass
    subprocess.call(["cp",
                     os.path.join(SAMPLE_ASSIGNMENT_CONFIG, "config.json"),
                     os.path.join(test.testcase_path, "assignment_config")])


############################################################################
def cleanup(test):
    subprocess.call(["rm", "-rf",
                     os.path.join(test.testcase_path, "data")])
    os.mkdir(os.path.join(test.testcase_path, "data"))


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
def one_docx(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "sample_word_document.docx"),
                     os.path.join(test.testcase_path, "data", "sample_word_document.docx")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","grade.txt_one_docx","-b")
    test.json_diff("results.json","results.json_one_docx")


@testcase
def two_docx(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "sample_word_document.docx"),
                     os.path.join(test.testcase_path, "data", "sample_word_document.docx")])
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "another_word_document.docx"),
                     os.path.join(test.testcase_path, "data", "another_word_document.docx")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","grade.txt_two_docx","-b")
    test.json_diff("results.json","results.json_two_docx")


@testcase
def one_pdf(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "created_using_google_docs.pdf"),
                     os.path.join(test.testcase_path, "data", "created_using_google_docs.pdf")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","grade.txt_one_pdf","-b")
    test.json_diff("results.json","results.json_one_pdf")


@testcase
def one_docx_one_pdf(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "another_word_document.docx"),
                     os.path.join(test.testcase_path, "data", "another_word_document.docx")])
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "created_using_google_docs.pdf"),
                     os.path.join(test.testcase_path, "data", "created_using_google_docs.pdf")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","grade.txt_one_docx_one_pdf","-b")
    test.json_diff("results.json","results.json_one_docx_one_pdf")

    

@testcase
def one_txt(test):
    cleanup(test)
    subprocess.call(["cp",
                     os.path.join(SAMPLE_SUBMISSIONS, "just_a_text_file.txt"),
                     os.path.join(test.testcase_path, "data", "just_a_text_file.txt")])
    test.run_compile()
    test.run_run()
    test.run_validator()
    test.diff("grade.txt","grade.txt_one_txt","-b")
    test.json_diff("results.json","results.json_one_txt")


