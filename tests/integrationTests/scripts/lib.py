import sys
import os
import subprocess
import glob
import inspect

from collections import defaultdict

to_run = defaultdict(lambda: [], {})

# Run a single test case
def run_test(name):
    print "--- BEGIN TEST MODULE " + name.upper() + " ---"
    for f in to_run[name]:
        f()
    print "--- END TEST MODULE " + name.upper() + " ---"
    print

# Run every test currently loaded
def run_all():
    for key, val in to_run.iteritems():
        print "--- BEGIN TEST MODULE " + key.upper() + " ---"
        for f in val:
            f()
        print "--- END TEST MODULE " + key.upper() + " ---"
        print

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
    def compile_grading(self):
        print "  - Compiling grading code..."
        subprocess.call(["clang++",
            "-c", "-std=c++11",
            "-I" + os.path.join(self.testcase_path, "assignment_config")]
            + glob.glob(os.path.join(self.testcase_path, "..", "..", "src", "*.cpp")))
        subprocess.call(["mv"]
                + glob.glob(os.path.join(os.getcwd(), "*.o"))
                + [os.path.join(self.testcase_path, "build")])

    # Link a given executable provided a list of libraries to link against and
    # a file containing the main function. It is assumed that all source files
    # prefixed with "main_" define a main function.
    def link(self, target, libraries=[], objects=[]):
        subprocess.call(["clang++", "-std=c++11"]
                + ["-l" + lib for lib in libraries]
                + ["-o", os.path.join(self.testcase_path, "build", target)]
                + [os.path.join(self.testcase_path, "build", o) for o in objects]
                + [o for o in glob.glob(os.path.join(self.testcase_path, "build", "*.o")) if not "main" in o])

    # Helper functions to link the four current executables.
    def link_compile(self):
        print "  - Linking compile.out..."
        self.link("compile.out", libraries=["seccomp"], objects=["main_compile.o"])

    def link_configure(self):
        print "  - Linking configure.out..."
        self.link("configure.out", libraries=["seccomp"], objects=["main_configure.o"])

    def link_runner(self):
        print "  - Linking runner.out..."
        self.link("runner.out", libraries=["seccomp"], objects=["main_runner.o"])

    def link_validator(self):
        print "  - Linking validator.out..."
        self.link("validator.out", libraries=["seccomp"], objects=["main_validator.o"])

    # Run the validator using some sane arguments. Likely wants to be made much more
    # customizable (different submission numbers, multiple users, etc.)
    # TODO: Read "main" for other executables, determine what files they expect and
    # the locations in which they expect them given different inputs. Define functions
    # for compiler, configure, and runner.
    def run_validator(self):
        print "  - Running validator.out..."
        with open("/dev/null") as devnull:
            subprocess.call([os.path.join(self.testcase_path, "build", "validator.out"), "testassignment", "testuser", "1", "0"], \
                    cwd=os.path.join(self.testcase_path, "data"), stdout=devnull, stderr=devnull)

    # Run the UNIX diff command given a filename. The files are compared between the
    # data folder and the validation folder within the test package. For example,
    # running test.diff("foo.txt") within the test package "test_foo", the files
    # /var/local/autograde_tests/tests/test_foo/data/foo.txt and
    # /var/local/autograde_tests/tests/test_foo/validation/foo.txt will be compared.
    def diff(self, filename):
        print "  - Checking differences in file \"" + filename + "\"..."
        return_code = subprocess.call(["diff", "-b", os.path.join(self.testcase_path, "data", filename), os.path.join(self.testcase_path, "validation", filename)])
        if return_code == 1:
            print "  !!! File \"" + filename + "\" differs from expected value. !!!"

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
    def wrapper():
        print "* Starting test " + modname + "." + func.__name__ + "..."
        func(TestcaseWrapper(path))
        print "* Finished test " + modname + "." + func.__name__
    global to_run
    to_run[modname].append(wrapper)
    return wrapper
