#!/usr/bin/env bash

# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit
fi

SUBMITTY_REPOSITORY=/usr/local/submitty/GIT_CHECKOUT/Submitty

# We only need to reset the system only if we've installed pip3,
# which only happens after we've installed the system
if [ -x "$(command -v pip3)" ]; then
    pip3 install -U PyYAML
    python3 ${SUBMITTY_REPOSITORY}/.setup/bin/reset_system.py
fi

sudo bash ${SUBMITTY_REPOSITORY}/.setup/install_system.sh --vagrant ${@}

