#!/bin/bash

#################################################################
# PYTHON PACKAGE SETUP
#########################

CONF_DIR="${SUBMITTY_INSTALL_DIR}/config"
WORKER=$([[ $(jq -r '.worker' "${CONF_DIR}"/submitty.json) == "true" ]] && echo 1 || echo 0) || 0
VAGRANT=0

if [ -d "${SUBMITTY_REPOSITORY}/.vagrant" ]; then
    VAGRANT=1
fi

# All users should have access to these files
umask 022

VENV_PATH="${SUBMITTY_INSTALL_DIR}/venv"

if [[ ! -d $VENV_PATH ]]; then
    python3 -m venv "$VENV_PATH"
fi

source "${VENV_PATH}/bin/activate"

pip3 install -r "${SUBMITTY_REPOSITORY}"/.setup/pip/system_requirements.txt

if [ ${VAGRANT} == 1 ] && [ "${WORKER}" == 0 ] ; then
    pip3 install -r "${SUBMITTY_REPOSITORY}"/.setup/pip/vagrant_requirements.txt -r "${SUBMITTY_REPOSITORY}"/.setup/pip/dev_requirements.txt
fi
