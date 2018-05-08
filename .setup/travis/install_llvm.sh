#!/usr/bin/env bash

if [ "${CLANG_VER}" != "5.0" ]; then
    LLVM_ARCHIVE_PATH=${HOME}/clang+llvm.tar.xz

    wget http://releases.llvm.org/${CLANG_VER}/clang+llvm-${CLANG_VER}-x86_64-linux-gnu-ubuntu-14.04.tar.xz -O ${LLVM_ARCHIVE_PATH}
    mkdir ${HOME}/clang+llvm
    tar xf ${LLVM_ARCHIVE_PATH} -C ${HOME}/clang+llvm --strip-components 1
    export PATH=${HOME}/clang+llvm/bin:${PATH}
else
    sudo apt-get install clang-${CLANG_VER}
fi
