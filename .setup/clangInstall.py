import subprocess
import sys
import os
from shutil import copyfile

cwd = os.getcwd()

clangDir = os.path.expanduser("/usr/local/submitty/clang-llvm/")

# removing the clangDir if you have already run INSTALL_SUBMITTY.sh
subprocess.call(["rm", "-rf", clangDir])

if not os.path.exists(clangDir):
        os.mkdir(clangDir)

subprocess.call(["git", "clone", "http://llvm.org/git/llvm.git", clangDir + "llvm/"])
subprocess.call(["git", "clone", "http://llvm.org/git/clang.git", clangDir + "llvm/tools/clang"])
subprocess.call(["git", "clone", "http://llvm.org/git/clang-tools-extra.git", clangDir + "llvm/tools/clang/tools/extra/"])

# build clang
if not os.path.exists(clangDir + "build/"):
        os.mkdir(clangDir+ "build/")

os.chdir(clangDir + "build/")


subprocess.call(["cmake", "-G", "Ninja", "../llvm", "-DCMAKE_INSTALL_PREFIX=/usr/local/submitty/SubmittyAnalysisTools/tmp/llvm", "-DCMAKE_BUILD_TYPE=Release", "-DLLVM_TARGETS_TO_BUILD=X86", "-DCMAKE_C_COMPILER=/usr/bin/clang-3.8", "-DCMAKE_CXX_COMPILER=/usr/bin/clang++-3.8"])

cmd = "echo 'add_subdirectory(ASTMatcher)' >> /usr/local/submitty/clang-llvm/llvm/tools/clang/tools/extra/CMakeLists.txt"
cmd2 = "echo 'add_subdirectory(UnionTool)' >> /usr/local/submitty/clang-llvm/llvm/tools/clang/tools/extra/CMakeLists.txt"

os.system(cmd)
os.system(cmd2)
'''
subprocess.call(["ninja", "ASTMatcher", "UnionTool"])
astMatcherDir = os.path.expanduser(clangDir + "llvm/tools/clang/tools/extra/ASTMatcher/")
'''


