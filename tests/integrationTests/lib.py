from __future__ import print_function
from collections import defaultdict
import datetime
import inspect
import json
import os
import subprocess
import traceback
import sys
from pprint import pprint

if sys.version_info[0] == 3:
    xrange = range

# global variable available to be used by the test suite modules
SUBMITTY_INSTALL_DIR = "__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__"
SUBMITTY_TUTORIAL_DIR = "__INSTALL__FILLIN__SUBMITTY_TUTORIAL_DIR__"

GRADING_SOURCE_DIR =  SUBMITTY_INSTALL_DIR + "/src/grading"

LOG_FILE = None
LOG_DIR = SUBMITTY_INSTALL_DIR + "/test_suite/log"


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


class TestcaseFile:
    def __init__(self):
        self.prebuild = lambda: None
        self.testcases = []
        self.testcases_names = []

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


###################################################################################
###################################################################################
# Run the given list of test case names
def run_tests(names):
    totalmodules = len(names)
    for name in sorted(names):
        name = name.split(".")
        key = name[0]
        val = to_run[key]
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
            testcases = []
            if len(name) > 1:
                for i in range(len(val.testcases)):
                    if str(val.testcases_names[i]).lower() == name[1].lower():
                        testcases.append(val.testcases[i])
            else:
                testcases = val.testcases
            total = len(testcases)
            for index, f in zip(xrange(1, total + 1), testcases):
                try:
                    f()
                except Exception as e:
                    with bold + red:
                        lineno = None
                        tb = traceback.extract_tb(sys.exc_info()[2])
                        for i in range(len(tb)-1, -1, -1):
                            if os.path.basename(tb[i][0]) == '__init__.py':
                                lineno = tb[i][1]
                        print("Testcase " + str(index) + " failed on line " + str(lineno) +
                              " with exception: ", e)
                        sys.exc_info()
                        total -= 1
            if total == len(testcases):
                with bold + green:
                    print("All testcases passed")
            else:
                with bold + red:
                    print(str(total) + "/" + str(len(testcases)) + " testcases passed")
                    modsuccess = False
        with bold:
            print("--- END TEST MODULE " + key.upper() + " ---")
        print()
        if not modsuccess:
            totalmodules -= 1
    if totalmodules == len(names):
        with bold + green:
            print("All " + str(len(names)) + " modules passed")
    else:
        with bold + red:
            print(str(totalmodules) + "/" + str(len(names)) + " modules passed")
        sys.exit(1)


# Run every test currently loaded
def run_all():
    run_tests(to_run.keys())


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
        except OSError as e:
            pass
        # copy the cmake file to the build directory
        subprocess.call(["cp",
            os.path.join(GRADING_SOURCE_DIR, "Sample_CMakeLists.txt"),
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
                self.debug_print("log/make_output.txt")
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
    def run_validator(self,user="testuser",subnum="1",subtime="0"):

        # VALIDATOR USAGE: validator <hw_id> <rcsid> <submission#> <time-of-submission>

        with open (os.path.join(self.testcase_path, "log", "validate_output.txt"), "w") as log:
            return_code = subprocess.call([os.path.join(self.testcase_path, "bin", "validate.out"),
                "testassignment", user,subnum,subtime], #"testuser", "1", "0"],
                cwd=os.path.join(self.testcase_path, "data"), stdout=log, stderr=log)
            if return_code != 0:
                raise RuntimeError("Validator exited with exit code " + str(return_code))


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

        if (arg=="") :
            process = subprocess.Popen(["diff", filename1, filename2], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        elif (arg=="-b") :
            #ignore changes in white space
            process = subprocess.Popen(["diff", arg, filename1, filename2], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        else :
            raise RuntimeError("ARGUMENT "+arg+" TO DIFF NOT TESTED")
        out, err = process.communicate()
        if process.returncode == 1:
            raise RuntimeError("Difference between " + filename1 + " and " + filename2 +
                               " exited with exit code " + str(process.returncode) + '\n\nDiff:\n' + out)


    # Helpful for debugging make errors on travis
    def debug_print(self,f):
        filename=os.path.join(self.testcase_path,f)
        print ("\nDEBUG_PRINT: ",filename)
        if os.path.exists(filename):
            with open(filename, 'r') as fin:
                print (fin.read())
        else:
            print ("  < file does not exist >")

            
    # Loads 2 files, truncates them after specified number of lines,
    # and then checks to see if they match
    def diff_truncate(self, num_lines_to_compare, f1, f2=""):
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
            contents1 = file1.readlines()
        with open(filename2) as file2:
            contents2 = file2.readlines()
            
        # delete/truncate the file
        del contents1[num_lines_to_compare:]
        del contents2[num_lines_to_compare:]

        if contents1 != contents2:
            raise RuntimeError("Files " + filename1 + " and " + filename2 + " are different within the first " + num_lines_to_compare + " lines.")


    ###################################################################################
    def empty_file(self, f):
        # if no directory provided...
        if not os.path.dirname(f):
            f = os.path.join("data", f)
        filename = os.path.join(self.testcase_path, f)
        if not os.path.isfile(filename):
            raise RuntimeError("File " + f + " should exist")
        if os.stat(filename).st_size != 0:
            raise RuntimeError("File " + f + " should be empty")


    ###################################################################################
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
            contents1 = json.load(file1)
        with open(filename2) as file2:
            contents2 = json.load(file2)
        ordered1 = self.json_ordered(contents1)
        ordered2 = self.json_ordered(contents2)
        if ordered1 != ordered2:
            # NOTE: The ordered json has extra syntax....
            # so instead, print the original contents to a file and diff that
            # (yes clumsy)
            with open ('json_ordered_1.json','w') as outfile:
                json.dump(contents1,outfile,sort_keys=True,indent=4, separators=(',', ': '))
            with open ('json_ordered_2.json','w') as outfile:
                json.dump(contents2,outfile,sort_keys=True,indent=4, separators=(',', ': '))
            print ("\ndiff json_ordered_1.json json_ordered_2.json\n")
            process = subprocess.Popen(["diff", 'json_ordered_1.json', 'json_ordered_2.json'])
            raise RuntimeError("JSON files are different:  " + filename1 + " " + filename2)

    def empty_json_diff(self, f):
        # if no directory provided...
        if not os.path.dirname(f):
            f = os.path.join("data", f)
        filename1 = os.path.join(self.testcase_path, f)
        filename2 = os.path.join(SUBMITTY_INSTALL_DIR,"test_suite/integrationTests/data/empty_json_diff_file.json")
        return self.json_diff(filename1,filename2)


    ###################################################################################
    # remove the running time, and many of the system stack trace lines
    def simplify_junit_output(self,filename):
        if not os.path.isfile(filename):
            raise RuntimeError("File " + filename + " does not exist")
        simplified = []
        with open(filename,'r') as file:
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
                #sys.stdout.write("LINE: " + line)
                simplified.append(line)
        return simplified

    # Compares two junit output files, ignoring the run time
    def junit_diff(self, f1, f2=""):
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

        if self.simplify_junit_output(filename1) != self.simplify_junit_output(filename2):
            raise RuntimeError("JUNIT OUTPUT files " + filename1 + " and " + filename2 + " are different")




    ###################################################################################
    # remove the timestamp on the emma coverage report
    def simplify_emma_coverage(self,filename):
        if not os.path.isfile(filename):
            raise RuntimeError("File " + filename + " does not exist")
        simplified = []
        with open(filename,'r') as file:
            for line in file:
                if ' report, generated ' in line:
                    continue
                #sys.stdout.write("LINE: " + line)
                simplified.append(line)
        return simplified

    # Compares two emma coverage report files, ignoring the timestamp on the report
    def emma_coverage_diff(self, f1, f2=""):
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

        if self.simplify_emma_coverage(filename1) != self.simplify_emma_coverage(filename2):
            raise RuntimeError("JUNIT OUTPUT files " + filename1 + " and " + filename2 + " are different")



###################################################################################
###################################################################################
def prebuild(func):
    mod = inspect.getmodule(inspect.stack()[1][0])
    path = os.path.dirname(mod.__file__)
    modname = mod.__name__
    tw = TestcaseWrapper(path)
    def wrapper():
        print("* Starting prebuild for " + modname + "... ", end="")
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
    def wrapper():
        print("* Starting testcase " + modname + "." + func.__name__ + "... ", end="")
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
