#!/usr/bin/env bash

SUBMITTY_INSTALL_DIR=/usr/local/submitty
SUBMITTY_REPOSITORY=/usr/local/submitty/GIT_CHECKOUT_Submitty

apt-get update
apt-get install -qqy python python-dev python3 python3-dev
PY3_VERSION=$(python3 -V 2>&1 | sed -e "s/Python\ \([0-9].[0-9]\)\(.*\)/\1/")
apt-get install libpython${PY3_VERSION}
wget --tries=5 https://bootstrap.pypa.io/get-pip.py -O /tmp/get-pip.py
python2 get-pip.py
python3 get-pip.py
pip3 install PyYAML

python3 ${SUBMITTY_REPOSITORY}/.setup/bin/reset_system.py
sudo ${SUBMITTY_REPOSITORY}/.setup/install_system.sh

