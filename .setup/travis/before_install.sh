#!/usr/bin/env bash

# Execute this script using source so that PATH is updated for the rest of the build

# Set the Java version
jdk_switcher use default
# we have to unset this as the JVM will print a message on STDERR on any execution if this is set because somehow that makes sense I guess
unset _JAVA_OPTIONS

# Setup our python3 version to use for application. We try to use python 3.6.x if available, else fallback to
# python 3.5.x
PY_VERSION_3=$(pyenv versions | grep -oP "3.6.[0-9]" || pyenv versions | grep -oP "3.5.[0-9]")
pyenv global ${PY_VERSION_3}

mkdir -p ~/.local/bin
export PATH="${HOME}/.local/bin:${PATH}:${HOME}/.composer/vendor/bin:/usr/bin"

# Travis has a really bizarre setup of how its Python/PHP stuff isn't really accessible to the "root" user
# and so we have to do some janky workarounds to make it so that the travis, root, and submitty users all
# refer to the same thing when doing "python3", "pip3", "composer", etc.
sudo sed -i -e "s/env_reset/\!env_reset/g" /etc/sudoers
sudo sed -e "s?secure_path=\"?secure_path=\"/home/travis/.phpenv/shims:/opt/python/${PY_VERSION_3}/bin:${PATH}:?g" --in-place /etc/sudoers
