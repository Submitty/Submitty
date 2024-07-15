#!/usr/bin/env bash
set -ve

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

CI=0
if [ -f "${THIS_DIR}/../.github_actions_ci_flag" ]; then
    CI=1
fi

SUBMITTY_REPOSITORY=$(jq -r '.submitty_repository' "${CONF_DIR}/submitty.json")
SUBMITTY_INSTALL_DIR=$(jq -r '.submitty_install_dir' "${CONF_DIR}/submitty.json")
WORKER=$([[ $(jq -r '.worker' "${CONF_DIR}/submitty.json") == "true" ]] && echo 1 || echo 0)

# Because shellcheck is run with the python wrapper we need to disable the 'Not following' error
# shellcheck disable=SC1091
source "${THIS_DIR}/bin/versions.sh"

if [ "${WORKER}" == 0 ]; then
    ALL_DAEMONS=( submitty_websocket_server submitty_autograding_shipper submitty_autograding_worker submitty_daemon_jobs_handler )
    RESTART_DAEMONS=( submitty_websocket_server submitty_daemon_jobs_handler )
else
    ALL_DAEMONS=( submitty_autograding_worker )
    RESTART_DAEMONS=( )
fi

#############################################################
# Re-Read other variables from submitty.json and submitty_users.json
# (eventually will remove these from the /usr/local/submitty/.setup/INSTALL_SUBMITTY.sh script)

SUBMITTY_DATA_DIR=$(jq -r '.submitty_data_dir' "${SUBMITTY_INSTALL_DIR}/config/submitty.json")
# Worker does not need course builders so just use root
if [ "${WORKER}" == 0 ]; then
    COURSE_BUILDERS_GROUP=$(jq -r '.course_builders_group' "${SUBMITTY_INSTALL_DIR}/config/submitty_users.json")
else
    COURSE_BUILDERS_GROUP=root
fi
NUM_UNTRUSTED=$(jq -r '.num_untrusted' "${SUBMITTY_INSTALL_DIR}/config/submitty_users.json")
FIRST_UNTRUSTED_UID=$(jq -r '.first_untrusted_uid' "${SUBMITTY_INSTALL_DIR}/config/submitty_users.json")
FIRST_UNTRUSTED_GID=$(jq -r '.first_untrusted_gid' "${SUBMITTY_INSTALL_DIR}/config/submitty_users.json")
DAEMON_USER=$(jq -r '.daemon_user' "${SUBMITTY_INSTALL_DIR}/config/submitty_users.json")
DAEMON_GROUP=${DAEMON_USER}
DAEMON_UID=$(jq -r '.daemon_uid' "${SUBMITTY_INSTALL_DIR}/config/submitty_users.json")
DAEMON_GID=$(jq -r '.daemon_gid' "${SUBMITTY_INSTALL_DIR}/config/submitty_users.json")
PHP_USER=$(jq -r '.php_user' "${SUBMITTY_INSTALL_DIR}/config/submitty_users.json")
CGI_USER=$(jq -r '.cgi_user' "${SUBMITTY_INSTALL_DIR}/config/submitty_users.json")
# Worker does not have daemon PHP so just use daemon group
if [ "${WORKER}" == 0 ]; then
    DAEMONPHP_GROUP=$(jq -r '.daemonphp_group' "${SUBMITTY_INSTALL_DIR}/config/submitty_users.json")
else
    DAEMONPHP_GROUP="${DAEMON_GROUP}"
fi
DAEMONCGI_GROUP=$(jq -r '.daemoncgi_group' "${SUBMITTY_INSTALL_DIR}/config/submitty_users.json")
DAEMONPHPCGI_GROUP=$(jq -r '.daemonphpcgi_group' "${SUBMITTY_INSTALL_DIR}/config/submitty_users.json")
SUPERVISOR_USER=$(jq -r '.supervisor_user' "${SUBMITTY_INSTALL_DIR}/config/submitty_users.json")

export THIS_DIR
export CONF_DIR
export VAGRANT
export UTM
export CI
export SUBMITTY_REPOSITORY
export SUBMITTY_INSTALL_DIR
export WORKER
export ALL_DAEMONS
export RESTART_DAEMONS

export SUBMITTY_DATA_DIR
export COURSE_BUILDERS_GROUP
export NUM_UNTRUSTED
export FIRST_UNTRUSTED_UID
export FIRST_UNTRUSTED_GID
export DAEMON_USER
export DAEMON_GROUP
export DAEMON_UID
export DAEMON_GID
export PHP_USER
export CGI_USER
export DAEMONPHP_GROUP
export DAEMONCGI_GROUP
export DAEMONPHPCGI_GROUP
export SUPERVISOR_USER
