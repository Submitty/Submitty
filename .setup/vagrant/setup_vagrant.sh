#!/usr/bin/env bash

# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit
fi

DISTRO=$(lsb_release -i | sed -e "s/Distributor\ ID\:\t//g" | tr '[:upper:]' '[:lower:]')
SUBMITTY_INSTALL_DIR=/usr/local/submitty
SUBMITTY_REPOSITORY=/usr/local/submitty/GIT_CHECKOUT_Submitty

apt-get update
apt-get install -qqy python python-dev python3 python3-dev
PY3_VERSION=$(python3 -V 2>&1 | sed -e "s/Python\ \([0-9].[0-9]\)\(.*\)/\1/")
apt-get install libpython${PY3_VERSION}

# Check to see if pip is installed on this system, and if not, install it
# from bootstrap.pypi.io so that we have the latest version (installing from
# the repo will give us something out-of-date and hard to install/manage)
if [ ! -x "$(command -v pip)" ]; then
    wget -nv --tries=5 https://bootstrap.pypa.io/get-pip.py -O /tmp/get-pip.py
    python3 /tmp/get-pip.py
    rm -rf /tmp/get-pip.py
else
    pip3 install -U pip
fi

pip3 install -U PyYAML

python3 ${SUBMITTY_REPOSITORY}/.setup/bin/reset_system.py

sudo ${SUBMITTY_REPOSITORY}/.setup/install_system.sh --vagrant ${@}

