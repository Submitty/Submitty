from __future__ import print_function

import sys
import os
import subprocess
import inspect
import traceback
import json

from collections import defaultdict


# global variable available to be used by the test suite modules
global HSS_INSTALL_DIR 
HSS_INSTALL_DIR = "__INSTALL__FILLIN__HSS_INSTALL_DIR__"

grading_source_dir =  HSS_INSTALL_DIR + "/src/grading"

class TestcaseFile:
    def __init__(self):
        self.prebuild = lambda: None
        self.testcases = []

to_run = defaultdict(lambda: TestcaseFile(), {})

# Helpers for color
class ASCIIEscapeManager:
    def __init__(self, codes):
        self.codes = map(str, codes)
    def __enter__(self):
        sys.stdout.write("\033[" + ";".join(self.codes) + "m")
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


# Run a single test case
def run_test(name):
    with bold:
        print("--- BEGIN TEST MODULE " + name.upper() + " ---")
    cont = True
    try:
        print("* Starting compilation...")
        to_run[name].prebuild()
        to_run[name].wrapper.build()
        print("* Finished compilation")
    except Exception as e:
        print("Build failed with exception: %s" % e)
        cont = False
    if cont:
        total = len(to_run[name].testcases)
        for index, f in zip(xrange(1, len(to_run[name].testcases) + 1), to_run[name].testcases):
            try:
                f()
            except Exception as e:
                with bold + red:
                    print("Test #" + str(index) + " failed with exception:", e)
                    total -= 1
                    cont = False
        if total == len(to_run[name].testcases):
            with bold + green:
                print("All tests passed")
        else:
            with bold + red:
                print(str(total) + "/" + str(len(to_run[name].testcases)) + " tests passed")
                success = False
    with bold:
        print("--- END TEST MODULE " + name.upper() + " ---")
    print()
    if not cont:
        sys.exit(1)

# Run every test currently loaded
def run_all():
    success = True
    totalmodules = len(to_run.keys())
    for key, val in to_run.iteritems():
        modsuccess = True
        with bold:
            print("--- BEGIN TEST MODULE " + key.upper() + " ---")
        cont = True
        try:
            print("* Starting compilation...")
            val.prebuild()
            val.wrapper.build()
            print("* Finished compilation")
        except Exception as e:
            print("Build failed with exception: %s" % e)
            modsuccess = False
            cont = False
        if cont:
            total = len(val.testcases)
            for index, f in zip(xrange(1, total + 1), val.testcases):
                try:
                    f()
                except Exception as e:
                    with bold + red:
                        print("Test #" + str(index) + " failed with exception:", e)
                        total -= 1
            if total == len(val.testcases):
                with bold + green:
                    print("All tests passed")
            else:
                with bold + red:
                    print(str(total) + "/" + str(len(val.testcases)) + " tests passed")
                    modsuccess = False
                    success = False
        with bold:
            print("--- END TEST MODULE " + key.upper() + " ---")
        print()
        if not modsuccess:
            totalmodules -= 1
    if totalmodules == len(to_run.keys()):
        with bold + green:
            print("All modules passed")
    else:
        with bold + red:
            print(str(totalmodules) + "/" + str(len(to_run.keys())) + " modules passed")
        sys.exit(1)



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
        except OSError as e:
            pass
        # copy the cmake file to the build directory
        subprocess.call(["cp",
            os.path.join(grading_source_dir, "Sample_CMakeLists.txt"),
            os.path.join(self.testcase_path, "build", "CMakeLists.txt")])
        with open(os.path.join(self.testcase_path, "log", "cmake_output.txt"), "w") as cmake_output:
            return_code = subprocess.call(["cmake", "-DASSIGNMENT_INSTALLATION=OFF", "."],
                    cwd=os.path.join(self.testcase_path, "build"), stdout=cmake_output, stderr=cmake_output)
            if return_code != 0:
                raise RuntimeError("Build (cmake) exited with exit code " + str(return_code))
        with open(os.path.join(self.testcase_path, "log", "make_output.txt"), "w") as make_output:
            return_code = subprocess.call(["make"],
                    cwd=os.path.join(self.testcase_path, "build"), stdout=make_output, stderr=make_output)
            if return_code != 0:
                raise RuntimeError("Build (make) exited with exit code " + str(return_code))



    # Run compile.out using some sane arguments.
    def run_compile(self):
        with open (os.path.join(self.testcase_path, "log", "compile_output.txt"), "w") as log:
            return_code = subprocess.call([os.path.join(self.testcase_path, "bin", "compile.out"),
                "testassignment", "testuser", "1", "0"], \
                        cwd=os.path.join(self.testcase_path, "data"), stdout=log, stderr=log)
            if return_code != 0:
                raise RuntimeError("Compile exited with exit code " + str(return_code))


    # Run run.out using some sane arguments.
    def run_run(self):
        with open (os.path.join(self.testcase_path, "log", "run_output.txt"), "w") as log:
            return_code = subprocess.call([os.path.join(self.testcase_path, "bin", "run.out"),
                "testassignment", "testuser", "1", "0"], \
                        cwd=os.path.join(self.testcase_path, "data"), stdout=log, stderr=log)
            if return_code != 0:
                raise RuntimeError("run.out exited with exit code " + str(return_code))


    # Run the validator using some sane arguments. Likely wants to be made much more
    # customizable (different submission numbers, multiple users, etc.)
    # TODO: Read "main" for other executables, determine what files they expect and
    # the locations in which they expect them given different inputs.
    def run_validator(self):
        with open (os.path.join(self.testcase_path, "log", "validate_output.txt"), "w") as log:
            return_code = subprocess.call([os.path.join(self.testcase_path, "bin", "validate.out"),
                "testassignment", "testuser", "1", "0"],
                cwd=os.path.join(self.testcase_path, "data"), stdout=log, stderr=log)
            if return_code != 0:
                raise RuntimeError("Validator exited with exit code " + str(return_code))


    # Run the UNIX diff command given a filename. The files are compared between the
    # data folder and the validation folder within the test package. For example,
    # running test.diff("foo.txt") within the test package "test_foo", the files
    # /var/local/autograde_tests/tests/test_foo/data/foo.txt and
    # /var/local/autograde_tests/tests/test_foo/validation/foo.txt will be compared.
    def diff(self, f1, f2=""):
        # if only 1 filename provided...
        if not f2:
            f2 = f1
        # if no directory provided...
        if not os.path.dirname(f1):
            f1 = os.path.join("data", f1)
        if not os.path.dirname(f2):
            f2 = os.path.join("validation", f2)

        filename1 = os.path.join(self.testcase_path, f1)
        filename2 = os.path.join(self.testcase_path, f2)

        if not os.path.isfile(filename1):
            raise RuntimeError("File " + filename1 + " does not exist")
        if not os.path.isfile(filename2):
            raise RuntimeError("File " + filename2 + " does not exist")

        #return_code = subprocess.call(["diff", "-b", filename1, filename2]) #ignores changes in white space
        return_code = subprocess.call(["diff", filename1, filename2])
        if return_code == 1:
            raise RuntimeError("Difference between " + filename1 + " and " + filename2 + " exited with exit code " + str(return_code))


    # Helper function for json_diff.  Sorts each nested list.  Allows comparison.
    # Credit: Zero Piraeus.
    # http://stackoverflow.com/questions/25851183/how-to-compare-two-json-objects-with-the-same-elements-in-a-different-order-equa
    def json_ordered(self,obj):
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
        # if no directory provided...
        if not os.path.dirname(f1):
            f1 = os.path.join("data", f1)
        if not os.path.dirname(f2):
            f2 = os.path.join("validation", f2)

        filename1 = os.path.join(self.testcase_path, f1)
        filename2 = os.path.join(self.testcase_path, f2)

        if not os.path.isfile(filename1):
            raise RuntimeError("File " + filename1 + " does not exist")
        if not os.path.isfile(filename2):
            raise RuntimeError("File " + filename2 + " does not exist")

        with open(filename1) as file1:
            contents1 = json.loads(file1.read())
        with open(filename2) as file2:
            contents2 = json.loads(file2.read())
        if self.json_ordered(contents1) != self.json_ordered(contents2):
            raise RuntimeError("JSON files " + filename1 + " and " + filename2 + " are different")

    def empty_file(self, f):
        # if no directory provided...
        if not os.path.dirname(f):
            f = os.path.join("data", f)
        filename1 = os.path.join(self.testcase_path, f)
        if os.stat(filename1).st_size != 0:
            raise RuntimeError("File " + f + " should be empty")

    def empty_json_diff(self, f):
        # if no directory provided...
        if not os.path.dirname(f):
            f = os.path.join("data", f)
        filename1 = os.path.join(self.testcase_path, f)
        filename2 = os.path.join(HSS_INSTALL_DIR,"test_suite/integrationTests/scripts/empty_json_diff_file.json")
        return self.json_diff(filename1,filename2)



def prebuild(func):
    mod = inspect.getmodule(inspect.stack()[1][0])
    path = os.path.dirname(mod.__file__)
    modname = mod.__name__
    tw = TestcaseWrapper(path)
    def wrapper():
        print("* Starting prebuild for " + modname + "...")
        func(tw)
        print("* Finished prebuild for " + modname)
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
    def wrapper():
        print("* Starting test " + modname + "." + func.__name__ + "...")
        func(tw)
        print("* Finished test " + modname + "." + func.__name__)
    global to_run
    to_run[modname].wrapper = tw
    to_run[modname].testcases.append(wrapper)
    return wrapper
