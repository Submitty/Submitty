#!/bin/bash

#################################################################
# PYTHON PACKAGE SETUP
#########################

# All users should have access to these files
umask 022

VENV_PATH="${SUBMITTY_INSTALL_DIR}/venv"

if [[ ! -d $VENV_PATH ]]; then
    python3 -m venv $VENV_PATH
fi

source "$VENV_PATH/bin/activate"

pip3 install -r ${SUBMITTY_REPOSITORY}/.setup/pip/system_requirements.txt

if [ ${VAGRANT} == 1 ] && [ ${WORKER} == 0 ] ; then
    pip3 install -r ${SUBMITTY_REPOSITORY}/.setup/pip/vagrant_requirements.txt -r ${SUBMITTY_REPOSITORY}/.setup/pip/dev_requirements.txt
fi
