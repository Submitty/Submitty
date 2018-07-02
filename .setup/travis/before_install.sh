#!/usr/bin/env bash

# Set the Java version
jdk_switcher use default
# we have to unset this as the JVM will print a message on STDERR on any execution if this is set because somehow that makes sense I guess
unset _JAVA_OPTIONS

# Setup our python3 version to use for application. We try to use python 3.6.x if available, else fallback to
# python 3.5.x
PY_VERSION_3=$(pyenv versions | grep -oP "3.6.[0-9]" || pyenv versions | grep -oP "3.5.[0-9]")
pyenv global ${PY_VERSION_3}

export PATH="$PATH:$HOME/.composer/vendor/bin:/usr/bin"
sudo sed -e "s?secure_path=\"?secure_path=\"/home/travis/.phpenv/shims:/opt/python/${PY_VERSION_3}/bin:${PATH}:?g" --in-place /etc/sudoers
mkdir -p ~/.local/bin
export PATH=$HOME/.local/bin:$PATH
