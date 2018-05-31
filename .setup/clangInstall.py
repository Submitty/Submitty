import subprocess
import sys
import os
from shutil import copyfile

#Work in progress - fix this 
#print("don't run this - exiting")
#sys.exit(1)

cwd = os.getcwd()



clangDir = os.path.expanduser("/usr/local/submitty/clang-llvm/")

#removing the clangDir if you have already run INTSALL_SUBMITTY.sh
subprocess.call(["rm", "-rf", clangDir])

if not os.path.exists(clangDir):
        os.mkdir(clangDir)
subprocess.call(["git", "clone", "http://llvm.org/git/llvm.git", clangDir + "llvm/"])
subprocess.call(["git", "clone", "http://llvm.org/git/clang.git", clangDir + "llvm/tools/clang"])
subprocess.call(["git", "clone", "http://llvm.org/git/clang-tools-extra.git", clangDir + "llvm/tools/clang/tools/extra/"])



# use the standard package versions..

#cmake and ninja
#subprocess.call(["git", "clone", "https://github.com/martine/ninja.git", clangDir + "ninja/"])
#os.chdir(clangDir + "ninja/")
#subprocess.call(["git", "checkout", "release"])
#subprocess.call(["./bootstrap.py"])

#copyfile(clangDir + "ninja/ninja", "/usr/bin/ninja")
#subprocess.call(["chmod", "+x", "/usr/bin/ninja"])

#subprocess.call(["git", "clone", "https://gitlab.kitware.com/cmake/cmake.git", clangDir + "cmake/"])

#os.chdir(clangDir + "cmake/")
#subprocess.call(["./bootstrap"])
#subprocess.call(["make"])
#subprocess.call(["make", "install"])



#build clang
if not os.path.exists(clangDir + "build/"):
        os.mkdir(clangDir+ "build/")

os.chdir(clangDir + "build/")

subprocess.call(["cmake", "-G", "Ninja", "../llvm", "-DCMAKE_INSTALL_PREFIX=/usr/local/submitty/SubmittyAnalysisTools/tmp/llvm", "-DCMAKE_BUILD_TYPE=Release", "-DLLVM_TARGETS_TO_BUILD=X86", "-DCMAKE_C_COMPILER=/usr/bin/clang-3.8", "-DCMAKE_CXX_COMPILER=/usr/bin/clang++-3.8"])

# building everything is perhaps not necessary...   ?
#subprocess.call(["ninja", "install"])


cmd = "echo 'add_subdirectory(ASTMatcher)' >> /usr/local/submitty/clang-llvm/llvm/tools/clang/tools/extra/CMakeLists.txt"
cmd2 = "echo 'add_subdirectory(UnionTool)' >> /usr/local/submitty/clang-llvm/llvm/tools/clang/tools/extra/CMakeLists.txt"
os.system(cmd)
os.system(cmd2)
'''
<<<<<<< HEAD
# Just build targets we need
subprocess.call(["ninja", "install"])
#subprocess.call(["ninja", "ASTMatcher", "UnionTool"])
=======
# Just build targets we need
subprocess.call(["ninja", "ASTMatcher", "UnionTool"])
>>>>>>> 449eee90bc75faca0e10cc3b1c6312fd35732a30
# TODO/FIXME: add lines to "install" by copying from build dir



astMatcherDir = os.path.expanduser(clangDir + "llvm/tools/clang/tools/extra/ASTMatcher/")

if not os.path.exists(astMatcherDir):
        os.mkdir(astMatcherDir)

os.chdir(cwd)
'''
