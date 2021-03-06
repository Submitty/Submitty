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
if [ $? -ne 0 ]; then
    DISTRO=$(lsb_release -si | tr '[:upper:]' '[:lower:]')
    VERSION=$(lsb_release -sr | tr '[:upper:]' '[:lower:]')
    >&2 echo -e "
For whatever reason, Vagrant has failed to build. If reporting
an error to the developers, please be sure to also send the
build log of Vagrant located at:
.vagrant/install_${DISTRO}_${VERSION}.log.
"
fi
