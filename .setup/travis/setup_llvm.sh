#!/usr/bin/env bash

curl -sSL "http://apt.llvm.org/llvm-snapshot.gpg.key" | sudo -E apt-key add -
echo "deb http://apt.llvm.org/trusty/ llvm-toolchain-trusty-${CLANG_VER} main" | sudo tee -a /etc/apt/sources.list > /dev/null
sudo -E apt-get -yq update &>> ~/apt-get-update.log
