#!/usr/bin/env bash

# Execute this script using source so that PATH is updated for the rest of the build
# and that we have access to the variables

# If jdk_switcher exists, use it to set our JVM version, else assume the machine has a sane default
if [[ $(command -v jdk_switcher) ]]; then
    # If we ever needed to get the source for enabling jdk_switcher
    #wget https://raw.githubusercontent.com/michaelklishin/jdk_switcher/master/jdk_switcher.sh -O /tmp/jdk_switcher.sh
    #source /tmp/jdk_switcher.sh
    jdk_switcher use default
fi

# Set Node.JS version
nvm install 10
nvm use 10

# Set the Java version

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

sudo ln -s $(which python3) /usr/local/bin/python
sudo ln -s $(which python3) /usr/local/bin/python3

# Set GH token for Composer so that it does not have API problems. This is a new issue that
# has come up with Travis for whatever reason, and unfortunately, does mean that building
# forks and PRs from external collaborators might have issues.
if [ -n "${GH_TOKEN}" ]; then
    echo "Set GH token for composer"
    composer config --global github-oauth.github.com ${GH_TOKEN}
fi
