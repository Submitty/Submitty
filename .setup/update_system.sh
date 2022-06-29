#!/usr/bin/env bash

# Usage:
#   update_system.sh

# print commands as we execute and fail early
set -ev

# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit 1
fi


# PATHS
CURRENT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
CONF_DIR="${CURRENT_DIR}/../../../config"
WORKER=$([[ $(jq -r '.worker' ${CONF_DIR}/submitty.json) == "true" ]] && echo 1 || echo 0) || 0
VAGRANT=0

if [ -d "${CURRENT_DIR}/../.vagrant" ]; then
    VAGRANT=1
fi

#install DLL for zbar for QR processing
apt-get install libzbar0 --yes

##source ${CURRENT_DIR}/distro_setup/setup_distro.sh

#################################################################
# PYTHON PACKAGE SETUP
#########################

pip3 install -r ${CURRENT_DIR}/pip/system_requirements.txt

if [ ${VAGRANT} == 1 ] && [ ${WORKER} == 0 ] ; then
    pip3 install -r ${CURRENT_DIR}/pip/vagrant_requirements.txt
fi

echo "Done."
exit 0
