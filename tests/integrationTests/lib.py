from collections import defaultdict
import datetime
import inspect
import json
import os
import subprocess
import traceback
import sys
import shutil
from functools import wraps
from multiprocessing import Pool, cpu_count

from submitty_utils import submitty_schema_validator

# global variable available to be used by the test suite modules
# this file is at SUBMITTY_INSTALL_DIR/test_suite/integrationTests
SUBMITTY_INSTALL_DIR = os.path.realpath(
    os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', '..')
)

INTEGRATION_TEST_ROOT_FOLDER = os.path.join(
    SUBMITTY_INSTALL_DIR,
    "./test_suite/integrationTests/"
)

COMPILE_CONFIGURE_BIN_PATH = os.path.join(
    INTEGRATION_TEST_ROOT_FOLDER,
    'compile_configure_bin.sh'
)

CONFIGURE_BIN_PATH = os.path.join(
    INTEGRATION_TEST_ROOT_FOLDER, 
    "configure.bin"
)

BUILD_MAIN_CONFIGUE_PATH = os.path.join(
    INTEGRATION_TEST_ROOT_FOLDER,
    'build_main_configure.sh'
)

# Verify that this has been installed by just checking that this file is located in
# a directory next to the config directory which has submitty.json in it
if not os.path.exists(os.path.join(SUBMITTY_INSTALL_DIR, 'config', 'submitty.json')):
    raise SystemExit('You must install the test suite before being able to run it.')

SUBMITTY_TUTORIAL_DIR = os.path.join(
    SUBMITTY_INSTALL_DIR,
    "./GIT_CHECKOUT/Tutorial"
)

GRADING_SOURCE_DIR = os.path.join(
    SUBMITTY_INSTALL_DIR,
    "./src/grading"
)

LOG_FILE = None
LOG_DIR = os.path.join(
    SUBMITTY_INSTALL_DIR,
    "./test_suite/log"
)


def print(*args, **kwargs):
    global LOG_FILE
    if "sep" not in kwargs:
        kwargs["sep"] = " "
    if "end" not in kwargs:
        kwargs["end"] = '\n'

    message = kwargs["sep"].join(map(str, args)) + kwargs["end"]
    if LOG_FILE is None:
        # include a couple microseconds in string so that we have unique log file
        # per test run
        LOG_FILE = datetime.datetime.now().strftime('%Y%m%d%H%M%S%f')[:-3]
    with open(os.path.join(LOG_DIR, LOG_FILE), 'a') as write_file:
        write_file.write(message)
    sys.stdout.write(message)


# Concurrency note:
# To be able to work across process boundary, there must be no lambda expression
# within the TestcaseFile class, and all wrappers must be annotated with
# functools.wraps. This is a must for python to transfer the object to the worker
# process, for more please see python pickling.
# Note: there could still be lambdas and non-top level functions in client test
# case code, its just the TestcaseFile must be pickle-able
class TestcaseFile:
    def __init__(self):
        self.wrapper = type(None)  # Equivalent to lambda: None, but pickle-able
        self.prebuild = type(None)
        self.testcases = []
        self.testcases_names = []


to_run = defaultdict(lambda: TestcaseFile(), {})


# Helpers for color
class ASCIIEscapeManager:
    def __init__(self, codes):
        self.codes = list(map(str, codes))

    def __enter__(self):
        sys.stdout.write(f"\u001B[{';'.join(self.codes)}m")

    def __exit__(self, exc_type, exc_value, traceback):
        sys.stdout.write("\033[0m")

    def __add__(self, other):
        return ASCIIEscapeManager(self.codes + other.codes)


bold = ASCIIEscapeManager([1])
underscore = ASCIIEscapeManager([4])
blink = ASCIIEscapeManager([5])

black = ASCIIEscapeManager([30])
red = ASCIIEscapeManager([31])
green = ASCIIEscapeManager([32])
yellow = ASCIIEscapeManager([33])
blue = ASCIIEscapeManager([34])
magenta = ASCIIEscapeManager([35])
cyan = ASCIIEscapeManager([36])
white = ASCIIEscapeManager([37])


###################################################################################
###################################################################################
# Run the given list of test case names in parallel using multiprocessing
def run_tests(names):
    arguments = []  # Arguments for run_single_test(list<str>, TestcaseFile)
    for name in sorted(names):
        name = name.split(".")
        key = name[0]
        case = to_run[key]
        arguments.append((name, case))
    __setup()
    # Concurrency note:
    # Its best to use something close to cpu_count() here, as single-thread
    # performance is important for some modules that are compute-intensive,
    # they might timeout as a result of lack of single thread performance.
    # This can happen with python_simple_homework.infinite_loop_time_cutoff
    with Pool(cpu_count()) as p:
        test_result = p.starmap(__run_single_test_module, arguments)
    if False in test_result:
        with bold + red:
            print(f"{test_result.count(True)}/{len(test_result)} modules passed")
            sys.exit(1)
    else:
        with bold + green:
            print(f"All {len(names)} modules passed")

def __setup():
    return_code = subprocess.call([
        "/bin/bash", 
        COMPILE_CONFIGURE_BIN_PATH,
        SUBMITTY_INSTALL_DIR, 
        CONFIGURE_BIN_PATH
    ])
    if return_code != 0:
        raise RuntimeError(f"Failed to generate main configure: {return_code}")
    pass


# Concurrency note:
# Currently all tests are run in parallel per-number of threads on a module level.
# Different modules are run in parallel on different worker threads, but all the
# test cases within the same module is run on the same thread sequentially.

# Executes a single test module, this method is executed across process boundary,
# Not meant to be called externally.
def __run_single_test_module(name, case):
    key = name[0]
    with bold:
        print(f"--- BEGIN TEST MODULE {key.upper()} AT PROCESS {os.getpid()} ---")
    module_success = __compile_test_module(case)
    if module_success:
        testcases = __collect_test_cases(case, name)
        succeed_count = len(testcases)
        for index, f in zip(range(1, succeed_count + 1), testcases):
            test_case_success = __execute_test_case(index, f)
            if not test_case_success:
                succeed_count -= 1
        if succeed_count == len(testcases):
            with bold + green:
                print("All testcases passed")
        else:
            with bold + red:
                print(f"{succeed_count}/{len(testcases)} testcases passed")
                module_success = False
    with bold:
        print(f"--- END TEST MODULE {key.upper()} ---")
    print()
    return module_success


def __compile_test_module(case):
    try:
        print("* Starting compilation...")
        case.prebuild()
        case.wrapper.build()
        print("* Finished compilation")
        return True
    except Exception as e:
        print(f"Build failed with exception: {e}")
        return False


def __collect_test_cases(case, name):
    testcases = []
    if len(name) > 1:
        for i in range(len(case.testcases)):
            if str(case.testcases_names[i]).lower() == name[1].lower():
                testcases.append(case.testcases[i])
    else:
        testcases = case.testcases
    return testcases


# Concurrency note:
# For future integration test speed-ups, concurrency level can be
# increased if this __execute_test_case is executed in parallel.
# However test code will lose its ability to share states within a
# test module across test cases as they are run in executed in
# different processes.
def __execute_test_case(index, test_case):
    try:
        test_case()
        return True
    except Exception as e:
        with bold + red:
            lineno = None
            tb = traceback.extract_tb(sys.exc_info()[2])
            for i in range(len(tb) - 1, -1, -1):
                if os.path.basename(tb[i][0]) == '__init__.py':
                    lineno = tb[i][1]
            print(f"Testcase {index} failed on line {lineno} with exception: ", e)
            sys.exc_info()
        return False


# Run every test currently loaded
def run_all():
    run_tests(to_run.keys())


# Copy the files & directories from source to target
# it will create directories as needed
# it's ok if the target directory or subdirectories already exist
# it will overwrite files with the same name if they exist
def copy_contents_into(source, target):
    if not os.path.isdir(target):
        raise RuntimeError(f"ERROR: the target directory does not exist '{target}'")
    if os.path.isdir(source):
        for item in os.listdir(source):
            if os.path.isdir(os.path.join(source, item)):
                if os.path.isdir(os.path.join(target, item)):
                    # recurse
                    copy_contents_into(os.path.join(source, item), os.path.join(target, item))
                elif os.path.isfile(os.path.join(target, item)):
                    raise RuntimeError(f"ERROR: the target subpath is a file not a directory '{os.path.join(target, item)}'")
                else:
                    # copy entire subtree
                    shutil.copytree(os.path.join(source, item), os.path.join(target, item))
            else:
                if os.path.exists(os.path.join(target, item)):
                    os.remove(os.path.join(target, item))
                try:
                    shutil.copy(os.path.join(source, item), target)
                except:
                    raise RuntimeError(f"ERROR COPYING FILE: {os.path.join(source, item)} -> {os.path.join(target, item)}")


def move_only_files(source, target):
    source_files = os.listdir(source)
    for file_name in source_files:
        full_file_name = os.path.join(source, file_name)
        if os.path.isfile(full_file_name):
            shutil.move(full_file_name, target)


###################################################################################
###################################################################################
# Helper class used to remove the burden of paths from the testcase author.
# The path (in /var/local) of the testcase is provided to the constructor,
# and is subsequently used in all methods for compilation, linkage, etc.
# The resulting object is passed to each function defined within the testcase
# package. Typically, one would use the @testcase decorator defined below,
# as it uses inspection to fully handle all paths with no input from the
# testcase author.
class TestcaseWrapper:
    def __init__(self, path):
        self.testcase_path = path

    # Compile each .cpp file into an object file in the current directory. Those
    # object files are then moved into the appropriate directory in the /var/local
    # tree. Unfortunately, this presents some issues, for example non-grading
    # object files in that directory being moved and subsequently linked with the
    # rest of the program. gcc/clang do not provide an option to specify an output
    # directory when compiling source files in bulk in this manner. The solution
    # is likely to run the compiler with a different working directory alongside
    # using relative paths.

    def build(self):
        try:
            # the log directory will contain various log files
            os.mkdir(os.path.join(self.testcase_path, "log"))
            # the build directory will contain the intermediate cmake files
            os.mkdir(os.path.join(self.testcase_path, "build"))
            # the bin directory will contain the autograding executables
            os.mkdir(os.path.join(self.testcase_path, "bin"))
            # The data directory in which configure will be run. This is needed to
            # make complete_config.json for schema testing
            os.mkdir(os.path.join(self.testcase_path, "data"))
        except OSError as e:
            pass
        # copy the cmake file to the build directory
        subprocess.call(["cp",
                         os.path.join(GRADING_SOURCE_DIR, "Sample_CMakeLists.txt"),
                         os.path.join(self.testcase_path, "build", "CMakeLists.txt")])

        shutil.copy(BUILD_MAIN_CONFIGUE_PATH, os.path.join(self.testcase_path, "build"))

        # First, we need to compile and run configure.out
        with open(os.path.join(self.testcase_path, "log", "main_configure_build.txt"), "w") as configure_output:
            return_code = subprocess.call(["/bin/bash", "build_main_configure.sh", self.testcase_path, SUBMITTY_INSTALL_DIR, CONFIGURE_BIN_PATH],
                                          cwd=os.path.join(self.testcase_path, "build"), stdout=configure_output, stderr=configure_output)
            if return_code != 0:
                raise RuntimeError(f"Failed to generate main configure: {return_code}")

        with open(os.path.join(self.testcase_path, "log", "cmake_output.txt"), "w") as cmake_output:
            return_code = subprocess.call(["cmake", "-DASSIGNMENT_INSTALLATION=OFF", "."],
                                          cwd=os.path.join(self.testcase_path, "build"), stdout=cmake_output, stderr=cmake_output)
            if return_code != 0:
                raise RuntimeError(f"Build (cmake) exited with exit code {return_code}")
        with open(os.path.join(self.testcase_path, "log", "make_output.txt"), "w") as make_output:
            return_code = subprocess.call(["make"],
                                          cwd=os.path.join(self.testcase_path, "build"), stdout=make_output, stderr=make_output)
            if return_code != 0:
                self.debug_print("log/make_output.txt")
                raise RuntimeError(f"Build (make) exited with exit code {return_code}")

    # Run compile.out using some sane arguments.
    def run_compile(self):
        config_path = os.path.join(self.testcase_path, 'assignment_config', 'complete_config.json')
        with open(config_path, 'r') as infile:
            config = json.load(infile)
            my_testcases = config['testcases']

        data_folder = os.path.join(self.testcase_path, 'data')

        # We create a temporary data folder, so that we don't tarnish the original.
        tmp_data_folder = os.path.join(self.testcase_path, 'tmp_data')
        if os.path.isdir(tmp_data_folder):
            shutil.rmtree(tmp_data_folder)
        os.makedirs(tmp_data_folder)

        # Will hold the compiled files and their STDOUT/STDERRs
        tmp_comp_folder = os.path.join(self.testcase_path, 'tmp_comp')

        # Make the work folder used by run_run.
        if os.path.isdir(tmp_comp_folder):
            shutil.rmtree(tmp_comp_folder)
        os.makedirs(tmp_comp_folder)

        # Copy the data folder to the temp_data folder, then pull that into the tmp_comp_folder
        copy_contents_into(data_folder, tmp_data_folder)
        copy_contents_into(tmp_data_folder, tmp_comp_folder)

        executable_path_list = list()
        with open(os.path.join(self.testcase_path, "log", "compile_output.txt"), "w") as log:
            # We start counting from one.
            for testcase_num in range(1, len(my_testcases) + 1):
                my_testcase = my_testcases[testcase_num - 1]
                testcase_folder = os.path.join(tmp_comp_folder, my_testcase['testcase_id'])

                if 'type' in my_testcase:
                    if my_testcase['type'] != 'FileCheck' and my_testcase['type'] != 'Compilation':
                        continue

                    if my_testcase['type'] == 'Compilation':
                        if 'executable_name' in my_testcase:
                            provided_executable_list = my_testcase['executable_name']
                            if not isinstance(provided_executable_list, (list,)):
                                provided_executable_list = list([provided_executable_list])
                            for exe in provided_executable_list:
                                if exe.strip() == '':
                                    continue
                                executable_path = os.path.join(testcase_folder, exe)
                                executable_path_list.append((executable_path, exe))
                else:
                    continue

                # Make the tmp folder for this testcase.
                if os.path.isdir(testcase_folder):
                    shutil.rmtree(testcase_folder)
                os.makedirs(testcase_folder)

                copy_contents_into(tmp_data_folder, testcase_folder)

                return_code = subprocess.call(
                    [
                        os.path.join(self.testcase_path, "bin", "compile.out"),
                        "testassignment",
                        "testuser",
                        "1",
                        "0",
                        my_testcase['testcase_id']
                    ],
                    cwd=testcase_folder, stdout=log, stderr=log)

                if return_code != 0:
                    raise RuntimeError(f"Compile exited with exit code {return_code}")

        compiled_files_directory = os.path.join(self.testcase_path, 'compiled_files')

        # Don't trust that the developer properly cleaned up after themselves.
        if os.path.isdir(compiled_files_directory):
            shutil.rmtree(compiled_files_directory)
        os.makedirs(compiled_files_directory)

        # Move the compiled files into the compiled_files_directory
        for path, name in executable_path_list:
            if not os.path.isfile(path):
                continue
            target_path = os.path.join(compiled_files_directory, name)
            if not os.path.exists(target_path):
                os.makedirs(os.path.dirname(target_path), exist_ok=True)
            shutil.copy(path, target_path)

        # Create the work folder, which will be used by run_run.
        work_folder = os.path.join(self.testcase_path, 'work')
        if os.path.isdir(work_folder):
            shutil.rmtree(work_folder)
        os.makedirs(work_folder)

        # Move the test##/ files generated by compoliation into the work directory
        copy_contents_into(tmp_comp_folder, work_folder)
        shutil.rmtree(tmp_comp_folder)

    # Run run.out using some sane arguments.
    def run_run(self):
        config_path = os.path.join(self.testcase_path, 'assignment_config', 'complete_config.json')

        with open(config_path, 'r') as infile:
            config = json.load(infile)
        my_testcases = config['testcases']

        data_folder = os.path.join(self.testcase_path, 'data')
        tmp_data_folder = os.path.join(self.testcase_path, 'tmp_data')
        work_folder = os.path.join(self.testcase_path, 'work')
        compiled_files_directory = os.path.join(self.testcase_path, 'compiled_files')

        if os.path.isdir(tmp_data_folder):
            shutil.rmtree(tmp_data_folder)
        os.makedirs(tmp_data_folder)

        copy_contents_into(data_folder, tmp_data_folder)

        with open(os.path.join(self.testcase_path, "log", "run_output.txt"), "w") as log:
            # We start counting from one.
            for testcase_num in range(1, len(my_testcases) + 1):
                my_testcase = my_testcases[testcase_num - 1]

                if 'type' in my_testcases[testcase_num - 1]:
                    if my_testcase['type'] == 'FileCheck' or my_testcase['type'] == 'Compilation':
                        continue
                # Make the tmp folder for this testcase.
                testcase_folder = os.path.join(work_folder, my_testcase['testcase_id'])

                # Don't trust that the developer properly cleaned up after themselves.
                if os.path.isdir(testcase_folder):
                    shutil.rmtree(testcase_folder)
                os.makedirs(testcase_folder)
                copy_contents_into(tmp_data_folder, testcase_folder)
                copy_contents_into(compiled_files_directory, testcase_folder)

                return_code = subprocess.call(
                    [
                        os.path.join(self.testcase_path, "bin", "run.out"),
                        "testassignment",
                        "testuser",
                        "1",
                        "0",
                        my_testcase['testcase_id']
                    ],
                    cwd=testcase_folder, stdout=log, stderr=log)
                if return_code != 0:
                    raise RuntimeError(f"run.out exited with exit code {return_code}")

            # Copy the results to the data folder.
            copy_contents_into(work_folder, data_folder)
            copy_contents_into(compiled_files_directory, data_folder)
            shutil.rmtree(work_folder)
            shutil.rmtree(tmp_data_folder)
            # if os.path.isdir(compiled_files_directory):
            #     shutil.rmtree(compiled_files_directory)

    # Run the validator using some sane arguments. Likely wants to be made much more
    # customizable (different submission numbers, multiple users, etc.)
    # TODO: Read "main" for other executables, determine what files they expect and
    # the locations in which they expect them given different inputs.
    def run_validator(self, user="testuser", subnum="1", subtime="0"):
        # VALIDATOR USAGE: validator <hw_id> <rcsid> <submission#> <time-of-submission>
        with open(os.path.join(self.testcase_path, "log", "validate_output.txt"), "w") as log:
            return_code = subprocess.call([os.path.join(self.testcase_path, "bin", "validate.out"),
                                           "testassignment", user, subnum, subtime],  # "testuser", "1", "0"],
                                          cwd=os.path.join(self.testcase_path, "data"), stdout=log, stderr=log)
            if return_code != 0:
                raise RuntimeError(f"Validator exited with exit code {return_code}")

    ###################################################################################
    # Run the UNIX diff command given a filename. The files are compared between the
    # data folder and the validation folder within the test package. For example,
    # running test.diff("foo.txt") within the test package "test_foo", the files
    # /var/local/autograde_tests/tests/test_foo/data/foo.txt and
    # /var/local/autograde_tests/tests/test_foo/validation/foo.txt will be compared.
    def diff(self, f1, f2="", arg=""):
        # if only 1 filename provided...
        if not f2:
            f2 = f1

        f1 = os.path.join("data", f1)
        if not 'data' in os.path.split(os.path.split(f2)[0]):
            f2 = os.path.join("validation", f2)

        filename1 = os.path.join(self.testcase_path, f1)
        filename2 = os.path.join(self.testcase_path, f2)

        if not os.path.isfile(filename1):
            raise RuntimeError(f"File {filename1} does not exist")
        if not os.path.isfile(filename2):
            raise RuntimeError(f"File {filename2} does not exist")

        if arg == "":
            process = subprocess.Popen(["diff", filename1, filename2], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        elif arg == "-b":
            # Ignore changes in white space
            process = subprocess.Popen(["diff", arg, filename1, filename2], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        else:
            raise RuntimeError(f"ARGUMENT {arg} TO DIFF NOT TESTED")
        out, _ = process.communicate()
        out = out.decode('utf-8')
        if process.returncode == 1:
            raise RuntimeError(f"Difference between {filename1} and {filename2} "
                               f"exited with exit code {process.returncode}\n\n"
                               f"Diff:\n{out}")

    # Helpful for debugging make errors on CI
    def debug_print(self, f):
        filename = os.path.join(self.testcase_path, f)
        print("\nDEBUG_PRINT: ", filename)
        if os.path.exists(filename):
            with open(filename, 'r') as fin:
                print(fin.read())
        else:
            print("  < file does not exist >")

    # Loads 2 files, truncates them after specified number of lines,
    # and then checks to see if they match
    def diff_truncate(self, num_lines_to_compare, f1, f2=""):
        # if only 1 filename provided...
        if not f2:
            f2 = f1

        f1 = os.path.join("data", f1)
        if not 'data' in os.path.split(f2):
            f2 = os.path.join("validation", f2)

        filename1 = os.path.join(self.testcase_path, f1)
        filename2 = os.path.join(self.testcase_path, f2)

        if not os.path.isfile(filename1):
            raise RuntimeError(f"File {filename1} does not exist")
        if not os.path.isfile(filename2):
            raise RuntimeError(f"File {filename2} does not exist")

        with open(filename1) as file1:
            contents1 = file1.readlines()
        with open(filename2) as file2:
            contents2 = file2.readlines()

        # delete/truncate the file
        del contents1[num_lines_to_compare:]
        del contents2[num_lines_to_compare:]

        if contents1 != contents2:
            raise RuntimeError(f"Files {filename1} and {filename2} are different within the first {num_lines_to_compare} lines.")

    ###################################################################################
    def empty_file(self, f):
        # if no directory provided...
        # if not os.path.dirname(f):
        f = os.path.join("data", f)
        filename = os.path.join(self.testcase_path, f)
        if not os.path.isfile(filename):
            raise RuntimeError(f"File {filename} should exist")
        if os.stat(filename).st_size != 0:
            raise RuntimeError(f"File {filename} should be empty")

    ###################################################################################
    # Helper function for json_diff.  Sorts each nested list.  Allows comparison.
    # Credit: Zero Piraeus.
    # http://stackoverflow.com/questions/25851183/how-to-compare-two-json-objects-with-the-same-elements-in-a-different-order-equa
    def json_ordered(self, obj):
        if isinstance(obj, dict):
            return sorted((k, self.json_ordered(v)) for k, v in obj.items())
        if isinstance(obj, list):
            return sorted(self.json_ordered(x) for x in obj)
        else:
            return obj

    # Compares two json files allowing differences in file whitespace
    # (indentation, newlines, etc) and also alternate ordering of data
    # inside dictionary/key-value pairs
    def json_diff(self, f1, f2=""):
        # if only 1 filename provided...
        if not f2:
            f2 = f1
            f1 = os.path.join('validation', f1)
        else:
            f1 = os.path.join("data", f1)
        if not 'data' in os.path.split(f2):
            f2 = os.path.join("validation", f2)

        filename1 = os.path.join(self.testcase_path, f1)
        filename2 = os.path.join(self.testcase_path, f2)

        if not os.path.isfile(filename1):
            raise RuntimeError(f"File {filename1} does not exist")
        if not os.path.isfile(filename2):
            raise RuntimeError(f"File {filename2} does not exist")

        with open(filename1) as file1:
            contents1 = json.load(file1)
        with open(filename2) as file2:
            contents2 = json.load(file2)
        ordered1 = self.json_ordered(contents1)
        ordered2 = self.json_ordered(contents2)
        if ordered1 != ordered2:
            # NOTE: The ordered json has extra syntax....
            # so instead, print the original contents to a file and diff that
            # (yes clumsy)
            with open('json_ordered_1.json', 'w') as outfile:
                json.dump(contents1, outfile, sort_keys=True, indent=4, separators=(',', ': '))
            with open('json_ordered_2.json', 'w') as outfile:
                json.dump(contents2, outfile, sort_keys=True, indent=4, separators=(',', ': '))
            print("\ndiff json_ordered_1.json json_ordered_2.json\n")
            process = subprocess.Popen(["diff", 'json_ordered_1.json', 'json_ordered_2.json'])
            raise RuntimeError(f"JSON files are different:  {filename1} {filename2}")

    def empty_json_diff(self, f):
        f = os.path.join("data", f)
        filename1 = os.path.join(self.testcase_path, f)
        filename2 = os.path.join(SUBMITTY_INSTALL_DIR, "test_suite/integrationTests/data/empty_json_diff_file.json")
        return self.json_diff(filename1, filename2)

    ###################################################################################
    # remove the running time, and many of the system stack trace lines
    def simplify_junit_output(self, filename):
        if not os.path.isfile(filename):
            raise RuntimeError(f"File {filename} does not exist")
        simplified = []
        with open(filename, 'r') as file:
            for line in file:
                if 'Time' in line:
                    continue
                if 'org.junit' in line:
                    continue
                if 'sun.reflect' in line:
                    continue
                if 'java.lang' in line:
                    continue
                if 'java.net' in line:
                    continue
                if 'sun.misc' in line:
                    continue
                if '... ' in line and ' more' in line:
                    continue
                # sys.stdout.write(f"LINE: {line}")
                simplified.append(line)
        return simplified

    # Compares two junit output files, ignoring the run time
    def junit_diff(self, f1, f2=""):
        # if only 1 filename provided...
        if not f2:
            f2 = f1

        f1 = os.path.join("data", f1)
        if not 'data' in os.path.split(f2):
            f2 = os.path.join("validation", f2)

        filename1 = os.path.join(self.testcase_path, f1)
        filename2 = os.path.join(self.testcase_path, f2)

        if self.simplify_junit_output(filename1) != self.simplify_junit_output(filename2):
            raise RuntimeError(f"JUNIT OUTPUT files {filename1} and {filename2} are different")

    # Validate a configuration against the submitty complete_config_schema.json
    def validate_complete_config(self, config_path):
        schema_path = os.path.join(SUBMITTY_INSTALL_DIR, 'bin', 'json_schemas', 'complete_config_schema.json')
        try:
            submitty_schema_validator.validate_complete_config_schema_using_filenames(config_path, schema_path)
        except submitty_schema_validator.SubmittySchemaException as s:
            s.print_human_readable_error()
            raise

        ###################################################################################


# Decorators
###################################################################################
def prebuild(func):
    mod = inspect.getmodule(inspect.stack()[1][0])
    path = os.path.dirname(mod.__file__)
    modname = mod.__name__
    tw = TestcaseWrapper(path)

    @wraps(func)  # Allows pickling for this lambda
    def wrapper():
        print(f"* Starting prebuild for {modname}... ", end="")
        func(tw)
        print("Done")

    global to_run
    to_run[modname].wrapper = tw
    to_run[modname].prebuild = wrapper
    return wrapper


# Decorator function using some inspection trickery to determine paths
def testcase(func):
    # inspect.stack() gets the current program stack. Index 1 is one
    # level up from the current stack frame, which in this case will
    # be the frame of the function calling this decorator. The first
    # element of that tuple is a frame object, which can be passed to
    # inspect.getmodule to fetch the module associated with that frame.
    # From there, we can get the path of that module, and infer the rest
    # of the required information.
    mod = inspect.getmodule(inspect.stack()[1][0])
    path = os.path.dirname(mod.__file__)
    modname = mod.__name__
    tw = TestcaseWrapper(path)

    @wraps(func)  # Allows pickling for this lambda
    def wrapper():
        print(f"* Starting testcase {modname}.{func.__name__}... ", end="")
        try:
            func(tw)
            with bold + green:
                print("PASSED")
        except Exception as e:
            with bold + red:
                print("FAILED")
            # blank raise raises the last exception as is
            raise

    global to_run
    to_run[modname].wrapper = tw
    to_run[modname].testcases.append(wrapper)
    to_run[modname].testcases_names.append(func.__name__)
    return wrapper
