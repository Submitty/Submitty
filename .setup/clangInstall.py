import subprocess
import sys
import os
from shutil import copyfile

cwd = os.getcwd()

clangDir = os.path.expanduser("/usr/local/submitty/clang-llvm/")


if not os.path.exists(clangDir):
        os.mkdir(clangDir)
subprocess.call(["git", "clone", "http://llvm.org/git/llvm.git", clangDir + "llvm/"])
subprocess.call(["git", "clone", "http://llvm.org/git/clang.git", clangDir + "llvm/tools/clang"])
subprocess.call(["git", "clone", "http://llvm.org/git/clang-tools-extra.git", clangDir + "llvm/tools/clang/tools/extra/"])

#cmake and ninja
subprocess.call(["git", "clone", "https://github.com/martine/ninja.git", clangDir + "ninja/"])
os.chdir(clangDir + "ninja/")
subprocess.call(["git", "checkout", "release"])
subprocess.call(["./bootstrap.py"])


copyfile(clangDir + "ninja/ninja", "/usr/bin/ninja")
subprocess.call(["chmod", "+x", "/usr/bin/ninja"])

subprocess.call(["git", "clone", "https://gitlab.kitware.com/cmake/cmake.git", clangDir + "cmake/"])

os.chdir(clangDir + "cmake/")
subprocess.call(["./bootstrap"])
subprocess.call(["make"])
subprocess.call(["make", "install"])

#build clang
if not os.path.exists(clangDir + "build/"):
        os.mkdir(clangDir+ "build/")

os.chdir(clangDir + "build/")

subprocess.call(["cmake", "-G", "Ninja", "../llvm", "-DCMAKE_BUILD_TYPE=Release", "-DLLVM_TARGETS_TO_BUILD=X86", "-DCMAKE_C_COMPILER=/usr/bin/clang-3.8", "-DCMAKE_CXX_COMPILER=/usr/bin/clang++-3.8"])

subprocess.call(["ninja"])
subprocess.call(["ninja", "install"])

cmd = "echo 'add_subdirectory(ASTMatcher)' >> ~/clang-llvm/llvm/tools/clang/tools/extra/CMakeLists.txt"
os.system(cmd)

os.chdir(cwd)
