import sys
import os
import subprocess
import glob
import inspect

from collections import defaultdict

to_run = defaultdict(lambda: [], {})

def run_all():
    for key, val in to_run.iteritems():
        print "--- BEGIN TEST MODULE " + key.upper() + " ---"
        for f in val:
            f()
        print "--- END TEST MODULE " + key.upper() + " ---"
        print

class TestcaseWrapper:
    def __init__(self, path):
        self.testcase_path = path

    def compile_grading(self):
        print "  - Compiling grading code..."
        subprocess.call(["clang++", "-c", "-std=c++11", "-I" + os.path.join(self.testcase_path, "assignment_config")]
                + glob.glob(os.path.join(self.testcase_path, "..", "..", "src", "*.cpp")))
        subprocess.call(["mv"] + glob.glob(os.path.join(os.getcwd(), "*.o")) + [os.path.join(self.testcase_path, "build")])

    def link(self, target, libraries=[], objects=[]):
        subprocess.call(
                ["clang++", "-std=c++11"]
                + ["-l" + lib for lib in libraries]
                + ["-o", os.path.join(self.testcase_path, "build", target)]
                + [os.path.join(self.testcase_path, "build", o) for o in objects]
                + [o for o in glob.glob(os.path.join(self.testcase_path, "build", "*.o")) if not "main" in o])

    def link_compile(self):
        print "  - Linking \"compile.out\"..."
        self.link("compile.out", libraries=["seccomp"], objects=["main_compile.o"])

    def link_configure(self):
        print "  - Linking \"configure.out\"..."
        self.link("configure.out", libraries=["seccomp"], objects=["main_configure.o"])

    def link_runner(self):
        print "  - Linking \"runner.out\"..."
        self.link("runner.out", libraries=["seccomp"], objects=["main_runner.o"])

    def link_validator(self):
        print "  - Linking \"validator.out\"..."
        self.link("validator.out", libraries=["seccomp"], objects=["main_validator.o"])

    def run_validator(self):
        print "  - Running \"validator.out\"..."
        with open("/dev/null") as devnull:
            subprocess.call([os.path.join(self.testcase_path, "build", "validate.out"), "testassignment", "testuser", "1", "0"],
                    cwd=os.path.join(self.testcase_path, "data"), stdout=devnull, stderr=devnull)

    def diff(self, filename):
        print "  - Running \"validator.out\"..."
        return_code = subprocess.call(["diff", "-b", os.path.join(self.testcase_path, "data", filename), os.path.join(self.testcase_path, "validation", filename)])
        if return_code == 1:
            print "  !!! File \"" + filename + "\" differs from expected value. !!!"

def testcase(func):
    path = os.path.dirname(inspect.getmodule(inspect.stack()[1][0]).__file__)
    modname = inspect.getmodule(inspect.stack()[1][0]).__name__
    def wrapper():
        print "* Starting test \"" + modname + "." + func.__name__ + "\"..."
        func(TestcaseWrapper(path))
        print "* Finished test \"" + modname + "." + func.__name__ + "\""
    global to_run
    to_run[modname].append(wrapper)
    return wrapper
