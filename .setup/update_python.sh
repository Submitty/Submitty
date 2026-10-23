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

# A release upgrade swaps the system Python under an existing venv, whose packages
# then sit in the old version's site-packages, so rebuild the venv when the versions differ.
PYTHON_VERSION=$(python3 -c 'import sys; print("%d.%d" % sys.version_info[:2])')
VENV_VERSION=$(sed -n 's/^version = \([0-9]*\.[0-9]*\).*/\1/p' "${VENV_PATH}/pyvenv.cfg" 2>/dev/null)
if [[ "$VENV_VERSION" != "$PYTHON_VERSION" ]]; then
    echo "Building the venv for Python ${PYTHON_VERSION} (found: ${VENV_VERSION:-none})"
    python3 -m venv --clear "$VENV_PATH"
fi

# shellcheck disable=SC1091
source "${VENV_PATH}/bin/activate"

pip3 install -r "${SUBMITTY_REPOSITORY}"/.setup/pip/system_requirements.txt

if [ ${VAGRANT} == 1 ] && [ "${WORKER}" == 0 ] ; then
    pip3 install -r "${SUBMITTY_REPOSITORY}"/.setup/pip/vagrant_requirements.txt -r "${SUBMITTY_REPOSITORY}"/.setup/pip/dev_requirements.txt
fi
