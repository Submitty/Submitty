#!/usr/bin/env bash

# We assume a relative path from this repository to the installation
# directory and configuration directory.
THIS_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null && pwd )/.."
CONF_DIR="${THIS_DIR}/../../../config"

VAGRANT=0
if [ -d "${THIS_DIR}/../.vagrant" ]; then
    VAGRANT=1
fi

UTM=0
if [ -d "${THIS_DIR}/../.utm" ]; then
    UTM=1
fi


SUBMITTY_REPOSITORY=$(jq -r '.submitty_repository' "${CONF_DIR}/submitty.json")
SUBMITTY_INSTALL_DIR=$(jq -r '.submitty_install_dir' "${CONF_DIR}/submitty.json")
WORKER=$([[ $(jq -r '.worker' "${CONF_DIR}/submitty.json") == "true" ]] && echo 1 || echo 0)

source "${THIS_DIR}/bin/versions.sh"

if [ "${WORKER}" == 0 ]; then
    ALL_DAEMONS=( submitty_websocket_server submitty_autograding_shipper submitty_autograding_worker submitty_daemon_jobs_handler )
    RESTART_DAEMONS=( submitty_websocket_server submitty_daemon_jobs_handler )
else
    ALL_DAEMONS=( submitty_autograding_worker )
    RESTART_DAEMONS=( )
fi

export THIS_DIR
export CONF_DIR
export VAGRANT
export UTM
export SUBMITTY_REPOSITORY
export SUBMITTY_INSTALL_DIR
export WORKER
export ALL_DAEMONS
export RESTART_DAEMONS
