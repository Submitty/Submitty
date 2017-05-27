#!/usr/bin/env bash

SUBMITTY_INSTALL_DIR=/usr/local/submitty
SUBMITTY_REPOSITORY=/usr/local/submitty/GIT_CHECKOUT_Submitty

apt-get update
apt-get install -qqy python python-pip python-dev python3 python3-pip python3-dev libpython3.5
pip2 install -U pip
pip3 install -U pip
pip3 install PyYAML

python3 ${SUBMITTY_REPOSITORY}/.setup/bin/reset_system.py
source ${SUBMITTY_REPOSITORY}/.setup/install_system.sh

