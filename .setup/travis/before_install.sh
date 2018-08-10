#!/usr/bin/env bash

# Execute this script using source so that PATH is updated for the rest of the build

# Set the Java version
jdk_switcher use default
# we have to unset this as the JVM will print a message on STDERR on any execution if this is set because somehow that makes sense I guess
unset _JAVA_OPTIONS

# Setup our python3 version to use for application. We try to use python 3.6.x if available, else fallback to
# python 3.5.x, but only when we're not explicitly building for python
if [ -z ${TRAVIS_PYTHON_VERSION} ]; then
    PY_VERSION_2=$(pyenv versions | grep -oP "2.7.[0-9]{1,}")
    PY_VERSION_3=$(pyenv versions | grep -oP "3.[0-9]{1,}.[0-9]{1,}" | tail -1)
    pyenv global ${PY_VERSION_3} ${PY_VERSION_2}
fi

mkdir -p ~/.local/bin

# Travis has a really bizarre setup of how its Python/PHP stuff isn't really accessible to the "root" user
# and so we have to do some janky workarounds to make it so that the travis, root, and submitty users all
# refer to the same thing when doing "python3", "pip3", "composer", etc.
sudo sed -i -e "s/env_reset/\!env_reset/g" /etc/sudoers
sudo sed -i -e "s?secure_path=\"?secure_path=\"${PATH}:?g" /etc/sudoers
