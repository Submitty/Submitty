import sys
import os
import tempfile
import shutil
import subprocess

# TODO: Add documentation to github.io: grading_done, Generate Reports, run on Vagrant host, what to run
# TODO: Make issue to extend script to work on host or live server?


# Used to exit and remove the top-level Rainbow Grades test in tmp
def error_and_cleanup(tmp_path, message, error=-1):
    print(message)
    if os.path.exists(tmp_path):
        shutil.rmtree(tmp_path)
    sys.exit(error)


# Used for filter() to ignore time-sensitive lines in report JSON files
def remove_extra_raw_data_fields(raw_line):
    if 'grade_released_date' in raw_line:
        return False
    if 'last_update' in raw_line:
        return False
    return True


# Used for filter() to ignore time-sensitive lines in summary HTML files
def remove_info_last_updated(raw_line):
    # <em>Information last updated: Monday, June 12, 2017</em><br>
    if '<em>Information last updated:' in raw_line:
        return False
    return True


# Function wrapper for the body of the test
def csci1100_rainbow_grades_test():
    # Get the Submitty repo path
    script_path = os.path.dirname(os.path.realpath(__file__))

    # Verify resources exist, set up initial temporary directories and configuration
    print("Creating temporary RainbowGrades test directories")
    test_tmp = tempfile.mkdtemp("", "")
    print("Made new directory {}".format(test_tmp))

    if not os.path.exists(test_tmp):
        error_and_cleanup(test_tmp, "Failed to create temporary directory")

    rainbow_path = os.path.join(script_path, "..", "..", "RainbowGrades")
    if not os.path.exists(rainbow_path):
        error_and_cleanup(test_tmp, "Couldn't find Rainbow Grades source code")

    rainbow_tmp = os.path.join(test_tmp, "rainbow_grades")
    os.mkdir(rainbow_tmp)

    summary_tmp = os.path.join(test_tmp, "grade_summaries")
    os.mkdir(summary_tmp)

    grading_tmp = os.path.join(test_tmp, "grading")
    os.mkdir(grading_tmp)

    if not os.path.exists(rainbow_tmp) or not os.path.exists(summary_tmp):
        error_and_cleanup(test_tmp, "Failed to create temporary subdirectory")

    print("Copying Rainbow Grades code from Submitty to RainbowGrades")
    try:
        for f in os.listdir(rainbow_path):
            shutil.copy(os.path.join(rainbow_path, f), rainbow_tmp)
    except Exception as e:
        error_and_cleanup(test_tmp, "{}".format(e))

    # Copy non-standard files over
    try:
        shutil.copy(os.path.join(script_path, "MakefileHelperTest"), os.path.join(rainbow_tmp, "MakefileHelper"))
        shutil.copy(os.path.join(script_path, "Makefile_csci1100"), os.path.join(summary_tmp, "Makefile"))
        shutil.copy(os.path.join(script_path, "customization_csci1100.json"),
                    os.path.join(summary_tmp, "customization.json"))
        shutil.copy(os.path.join(script_path, "..", "..", "grading", "json_syntax_checker.py"),
                    os.path.join(grading_tmp, "json_syntax_checker.py"))
    except Exception as e:
        error_and_cleanup(test_tmp, "{}".format(e))

    # Update Makefile to use the temporary location of RainbowGrades
    try:
        make_file = open(os.path.join(summary_tmp, "Makefile"), 'r')
        make_file_contents = make_file.readlines()
        make_file.close()
        make_file = open(os.path.join(summary_tmp, "Makefile"), 'w')
        for line in make_file_contents:
            if len(line) >= 25 and line[:25] == "RAINBOW_GRADES_DIRECTORY=":
                make_file.write("RAINBOW_GRADES_DIRECTORY=" + rainbow_tmp + "\n")
            else:
                make_file.write(line)
        make_file.close()
    except Exception as e:
        error_and_cleanup(test_tmp, "{}".format(e))

    # Use the same method a user would to pull reports from Submitty to Rainbow Grades
    print("Attempting to rsync contents")
    os.chdir(summary_tmp)
    return_code = subprocess.call(["make", "pull"])

    if return_code != 0:
        error_and_cleanup(test_tmp, "Failed to rsync data", return_code)

    if not os.path.exists(os.path.join(summary_tmp, "raw_data")):
        error_and_cleanup(test_tmp, "Could not find raw_data folder after rsync'ing")

    # Extract the test version of the reports for comparison
    print("Extracting known raw data")
    known_raw_path = os.path.join(test_tmp, "raw_data")
    summary_raw_path = os.path.join(summary_tmp, "raw_data")
    os.mkdir(known_raw_path)
    return_code = subprocess.call(["tar", "-xf", os.path.join(script_path, "raw_data_10090542_csci1100.tar"),
                                   "-C", known_raw_path])
    if return_code != 0:
        error_and_cleanup(test_tmp, "Extracting raw data failed", return_code)
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
            file1 = open(filename1, 'r')
            contents1 = file1.readlines()
            file1.close()

            file2 = open(filename2, 'r')
            contents2 = file2.readlines()
            file2.close()
        except Exception as e:
            error_and_cleanup(test_tmp, "{}".format(e))

        # Use filters to avoid time-dependent fields and speed up comparison
        filter1 = filter(remove_extra_raw_data_fields, contents1)
        filter2 = filter(remove_extra_raw_data_fields, contents2)
        same_flag = True
        for x, y in zip(filter1, filter2):
            if x != y:
                same_flag = False
        if not same_flag:
            error_and_cleanup(test_tmp, "{} and {} differ".format(filename1, filename2))

    print("All raw files match")

    # PRECONDITION: Input at this point is verified. Test running Rainbow Grades.
    print("Running Rainbow Grades on rsync'd data. This could take several minutes.")
    make_output = ""
    try:
        make_output = subprocess.check_output(["make"])
    except subprocess.CalledProcessError as e:
        error_and_cleanup(test_tmp, "Make failed with code {}".format(e.returncode), e.returncode)

    if not os.path.exists(os.path.join(summary_tmp, "output.html")):
        error_and_cleanup(test_tmp, "Failed to create output.html")

    print("output.html generated")

    print("Checking summary files against expected summaries.")
    # Verify that a valid copy of output.html was sent to all_students_summary_html
    make_output = make_output.splitlines()
    make_output = make_output[-1].strip()  # Get the RUN COMMAND LINE
    make_output = make_output.split(b'/')
    make_output = make_output[-1]  # Get the name of the output.html file since it uses the date
    if not os.path.exists(os.path.join(summary_tmp, "all_students_summary_html", make_output)):
        error_and_cleanup(test_tmp, "Failed to find output file in all_students_summary_html")

    output_generated_contents = ""
    output_known_contents = ""
    try:
        output_generated_file = open(os.path.join(summary_tmp, "output.html"), 'r')
        output_known_file = open(os.path.join(script_path, "output_10090542_csci1100.html"), 'r')
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
    subprocess.call(["tar", "-xf", os.path.join(script_path, "individual_summary_10090542_csci1100.tar"),
                     "-C", known_individual_path])

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
            file1 = open(filename1, 'r')
            contents1 = file1.readlines()
            file1.close()

            file2 = open(filename2, 'r')
            contents2 = file2.readlines()
            file2.close()
        except Exception as e:
            error_and_cleanup(test_tmp, "{}".format(e))

        # Construct and use filters to ignore time-dependent contents during comparison
        filter1 = filter(remove_info_last_updated, contents1)
        filter2 = filter(remove_info_last_updated, contents2)
        same_flag = True
        for x, y in zip(filter1, filter2):
            if x != y:
                same_flag = False
        if not same_flag:
            error_and_cleanup(test_tmp, "{} and {} differ".format(filename1, filename2))

    print("All generated files match")

    # TODO: Add make push, and create a test for the Submitty-side "View Grades"

    # Cleanup generated directories/files
    print("Removing temporary directory")
    shutil.rmtree(test_tmp)

if __name__ == '__main__':
    csci1100_rainbow_grades_test()
    sys.exit(0)
