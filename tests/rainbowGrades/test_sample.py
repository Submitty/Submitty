#!/usr/bin/env python3
"""
This script tests Rainbow Grades functionality in a mostly end-to-end manner - it requires that a user has manually
run Generate Grade Summaries on the sample course prior to running the script. It checks that the reports can be copied, that
they match the expected reports (minus time-specific fields), that Rainbow Grades compiles correctly, and that the
grade summaries that are written out by Rainbow Grades match expectations. It does not currently check that a make push
will succeed or result in the correct website behavior, but this should be added in the future.
"""
import sys
import os
import tempfile
import shutil
import subprocess
from datetime import datetime

# Get paths required for testing
repository_path = "__INSTALL__FILLIN__SUBMITTY_REPOSITORY__"
script_path = os.path.join("__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__", "test_suite", "rainbowGrades")
runner_dir = os.path.join("__INSTALL__FILLIN__SUBMITTY_DATA_DIR__", "to_be_graded_batch")


def get_current_semester():
    """
    Given today's date, generates a three character code that represents the semester to use for
    courses such that the first half of the year is considered "Spring" and the last half is
    considered "Fall". The "Spring" semester  gets an S as the first letter while "Fall" gets an
    F. The next two characters are the last two digits in the current year.
    :return:
    """
    today = datetime.today()
    semester = "f" + str(today.year)[-2:]
    if today.month < 7:
        semester = "s" + str(today.year)[-2:]
    return semester



def error_and_cleanup(tmp_path, message, error=-1):
    """Used to exit and remove the top-level Rainbow Grades test in tmp.

    :param tmp_path: The path of the temporary directory used for testing
    :param message: An error message to be written to the terminal
    :param error: Which error code to return (default -1)
    :return: None
    """
    print("ERROR: " + message)
    # if os.path.isdir(tmp_path):
    #     shutil.rmtree(tmp_path)
    sys.exit(error)


def remove_extra_raw_data_fields(raw_line):
    """Used for filter() to ignore time-sensitive lines in report JSON files.

    :param raw_line: A line to filter
    :return: True if no time-sensitive fields are present in the line
    """
    if 'grade_released_date' in raw_line:
        return False
    if 'last_update' in raw_line:
        return False
    if 'date:' in raw_line:
        return False
    return True


def remove_info_last_updated(raw_line):
    """Used for filter() to ignore time-sensitive lines in summary HTML files.

    :param raw_line: A line to filter
    :return: True if no time-sensitive fields are present in the line
    """
    if '<em>Information last updated:' in raw_line:
        return False
    return True


def sample_rainbow_grades_test():
    """Function wrapper for the body of the test."""

    # Verify resources exist, set up initial temporary directories and configuration
    print("Creating temporary RainbowGrades test directories")
    test_tmp = tempfile.mkdtemp("", "",script_path)
    print("Made new directory {}".format(test_tmp))

    if not os.path.isdir(test_tmp):
        error_and_cleanup(test_tmp, "Failed to create temporary directory")

    for f in os.listdir(runner_dir):
        if "__sample__" in f:
            error_and_cleanup(test_tmp, "sample has assignments in the grading queue."
                              " Wait for the autograder to finish and then generate new grade summary reports"
                              "prior to re-running this test.")

    rainbow_path = os.path.join(repository_path, "RainbowGrades")
    if not os.path.isdir(rainbow_path):
        error_and_cleanup(test_tmp, "Couldn't find Rainbow Grades source code")

    rainbow_tmp = os.path.join(test_tmp, "rainbow_grades")
    os.mkdir(rainbow_tmp)

    summary_tmp = os.path.join(test_tmp, "grade_summaries")
    os.mkdir(summary_tmp)

    grading_tmp = os.path.join(test_tmp, "grading")
    os.mkdir(grading_tmp)

    if not os.path.isdir(rainbow_tmp) or not os.path.isdir(summary_tmp):
        error_and_cleanup(test_tmp, "Failed to create temporary subdirectory")

    print("Copying Rainbow Grades code from Submitty to RainbowGrades")
    try:
        for f in os.listdir(rainbow_path):
            shutil.copy(os.path.join(rainbow_path, f), rainbow_tmp)
    except Exception as e:
        print("Rainbow PAth: {} Rainbow tmp: {}".format(rainbow_path,rainbow_tmp))
        error_and_cleanup(test_tmp, "{}".format(e))

    # Copy non-standard files over
    print("Copying test-specific files")
    try:
        shutil.copy(os.path.join(script_path, "MakefileHelperTest"), os.path.join(rainbow_tmp, "MakefileHelper"))
        shutil.copy(os.path.join(script_path, "Makefile_sample"), os.path.join(summary_tmp, "Makefile"))
        shutil.copy(os.path.join("__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__", ".setup", "customization_sample.json"),
                    os.path.join(summary_tmp, "customization.json"))
        shutil.copy(os.path.join(repository_path, "grading", "json_syntax_checker.py"),
                    os.path.join(grading_tmp, "json_syntax_checker.py"))
    except Exception as e:
        error_and_cleanup(test_tmp, "{}".format(e))

    # Update Makefile to use the temporary location of RainbowGrades
    print("Updating Rainbow Grades Makefile")
    try:
        with open(os.path.join(summary_tmp, "Makefile"), 'r') as make_file:
            make_file_contents = make_file.readlines()
        with open(os.path.join(summary_tmp, "Makefile"), 'w') as make_file:
            for line in make_file_contents:
                if len(line) >= 25 and line[:25] == "RAINBOW_GRADES_DIRECTORY=":
                    make_file.write("RAINBOW_GRADES_DIRECTORY=" + rainbow_tmp + "\n")
                elif len(line) >= 18 and line[:18] == "REPORTS_DIRECTORY=":
                    make_file.write(os.path.join("REPORTS_DIRECTORY=__INSTALL__FILLIN__SUBMITTY_DATA_DIR__", "courses",
                                                 get_current_semester(), "sample", "reports") + "\n")
                else:
                    make_file.write(line)
    except Exception as e:
        error_and_cleanup(test_tmp, "{}".format(e))

    # Use the same method a user would to pull reports from Submitty to Rainbow Grades
    print("Attempting to rsync contents")
    os.chdir(summary_tmp)
    return_code = subprocess.call(["make", "pull"])

    if return_code != 0:
        error_and_cleanup(test_tmp, "Failed to rsync data (Error {})".format(return_code))

    if not os.path.isdir(os.path.join(summary_tmp, "raw_data")):
        error_and_cleanup(test_tmp, "Could not find raw_data folder after rsync'ing")

    # Extract the test version of the reports for comparison
    print("Extracting known raw data")
    known_raw_path = os.path.join(test_tmp, "raw_data")
    summary_raw_path = os.path.join(summary_tmp, "raw_data")
    os.mkdir(known_raw_path)
    return_code = subprocess.call(["tar", "-xf", os.path.join(script_path, "raw_data_10090542_sample.tar"),
                                   "-C", known_raw_path])
    if return_code != 0:
        error_and_cleanup(test_tmp, "Extracting raw data failed (Error {}".format(return_code))
    print("Comparing known raw data to rsync'd raw data")

    # Construct a list of all files in the Submitty and test versions of reports to make sure the name/number matches
    known_files = []
    summary_files = []
    for f in os.listdir(known_raw_path):
        known_files.append(f)
    for f in os.listdir(summary_raw_path):
        summary_files.append(f)

    if len(known_files) != len(summary_files):
        file_diff = len(known_files) - len(summary_files)
        if len(known_files) > len(summary_files):
            error_and_cleanup(test_tmp,
                              "There are {} more files in the rsync'd raw_data than expected.".format(file_diff))
        else:
            error_and_cleanup(test_tmp,
                              "There are {} fewer files in the rsync'd raw_data than expected.".format(-1 * file_diff))

    # Verify the content (except for time-dependent fields) of Submitty raw_data files match with test version
    for f in known_files:
        contents1 = ""
        contents2 = ""
        filename1 = os.path.join(known_raw_path, f)
        filename2 = os.path.join(summary_raw_path, f)

        try:
            with open(filename1, 'r') as file1:
                contents1 = file1.readlines()

            with open(filename2, 'r') as file2:
                contents2 = file2.readlines()
        except Exception as e:
            error_and_cleanup(test_tmp, "{}".format(e))

        # Use filters to avoid time-dependent fields and speed up comparison
        filter1 = filter(remove_extra_raw_data_fields, contents1)
        filter2 = filter(remove_extra_raw_data_fields, contents2)
        for x, y in zip(filter1, filter2):
            if x != y:
                error_and_cleanup(test_tmp, "{} and {} differ".format(filename1, filename2))

    print("All raw files match")

    # PRECONDITION: Input at this point is verified. Test running Rainbow Grades.
    print("Running Rainbow Grades on rsync'd data. This could take several minutes.")
    make_output = ""
    try:
        make_output = subprocess.check_output(["make"])
    except subprocess.CalledProcessError as e:
        error_and_cleanup(test_tmp, "Make failed with code {}".format(e.returncode))

    if not os.path.isfile(os.path.join(summary_tmp, "output.html")):
        error_and_cleanup(test_tmp, "Failed to create output.html")

    print("output.html generated")

    print("Checking summary files against expected summaries.")
    # Verify that a valid copy of output.html was sent to all_students_summary_html
    make_output = make_output.splitlines()
    make_output = make_output[-1].strip()  # Get the RUN COMMAND LINE
    make_output = make_output.split('/')
    make_output = make_output[-1]  # Get the name of the output.html file since it uses the date
    if not os.path.isfile(os.path.join(summary_tmp, "all_students_summary_html", make_output)):
        error_and_cleanup(test_tmp, "Failed to find output file in all_students_summary_html")

    output_generated_contents = ""
    output_known_contents = ""
    try:
        with open(os.path.join(summary_tmp, "output.html"), 'r') as output_generated_file,\
             open(os.path.join(script_path, "output_10090542_sample.html"), 'r') as output_known_file:
            output_generated_contents = output_generated_file.read()
            output_known_contents = output_known_file.read()
    except Exception as e:
        error_and_cleanup(test_tmp, "{}".format(e))

    if output_generated_contents != output_known_contents:
        error_and_cleanup(test_tmp, "Generated output.html did not match expected output.html")

    # Extract test version of individual_grade_summary_html files
    known_individual_path = os.path.join(test_tmp, "individual_summary_html")
    summary_individual_path = os.path.join(summary_tmp, "individual_summary_html")
    os.mkdir(known_individual_path)
    return_code = subprocess.call(["tar", "-xf", os.path.join(script_path, "individual_summary_10090542_sample.tar"),
                                   "-C", known_individual_path])
    if return_code != 0:
        error_and_cleanup(test_tmp, "Extracting raw data failed (Error {}".format(return_code))

    # Construct lists of generated and test individual_grade_summary_html files
    known_files = []
    summary_files = []
    for f in os.listdir(known_individual_path):
        known_files.append(f)
    for f in os.listdir(summary_individual_path):
        summary_files.append(f)

    # Check that the name and number of individual_grade_summary_html files are the same
    if len(known_files) != len(summary_files):
        file_diff = len(known_files) - len(summary_files)
        if len(known_files) > len(summary_files):
            error_and_cleanup(test_tmp, "There are {} more files in the generated ".format(file_diff) +
                              "individual_summary_html than expected.")
        else:
            error_and_cleanup(test_tmp, "There are {} fewer files in the generated ".format(-1*file_diff) +
                              "individual_summary_html than expected.")

    # Compare the contents (excluding time-sensitive parts) of generated and test individual_grade_summary_html files
    # TODO: Currently not checking generated personal message files (for seating/materials)
    for f in known_files:
        # Skip any files that don't end in summary.html (i.e. messages.json/.html)
        if f[-12:] != "summary.html":
            continue
        filename1 = os.path.join(known_individual_path, f)
        filename2 = os.path.join(summary_individual_path, f)
        contents1 = ""
        contents2 = ""

        try:
            with open(filename1, 'r') as file1:
                contents1 = file1.readlines()

            with open(filename2, 'r') as file2:
                contents2 = file2.readlines()
        except Exception as e:
            error_and_cleanup(test_tmp, "{}".format(e))

        # Construct and use filters to ignore time-dependent contents during comparison
        filter1 = filter(remove_info_last_updated, contents1)
        filter2 = filter(remove_info_last_updated, contents2)
        for x, y in zip(filter1, filter2):
            if x != y:
                error_and_cleanup(test_tmp, "{} and {} differ".format(filename1, filename2))

    print("All generated files match")

    # TODO: Add make push, and create a test for the Submitty-side "View Grades"

    # Cleanup generated directories/files
    # print("Removing temporary directory")
    # shutil.rmtree(test_tmp)

if __name__ == '__main__':
    sample_rainbow_grades_test()
