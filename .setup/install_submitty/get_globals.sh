#!/usr/bin/env bash
set -ve

# This script has one required argument, config=<config dir>.

# Get arguments
for cli_arg in "$@"
do
    if [[ $cli_arg =~ ^config=.* ]]; then
        SUBMITTY_CONFIG_DIR="$(readlink -f "$(echo "$cli_arg" | cut -f2 -d=)")"
    fi
done

if [ -z "${SUBMITTY_CONFIG_DIR}" ]; then
    echo "ERROR: This script requires a config dir argument"
    echo "Usage: ${BASH_SOURCE[0]} config=<config dir>"
    exit 1
fi

SUBMITTY_INSTALL_DIR="$(jq -r '.submitty_install_dir' "${SUBMITTY_CONFIG_DIR:?}/submitty.json")"
SUBMITTY_REPOSITORY="$(jq -r '.submitty_repository' "${SUBMITTY_CONFIG_DIR:?}/submitty.json")"

# every github ci action has is_ci.
# all vagrant actions, including vagrant on ci, have is_vagrant.
IS_WORKER="$([[ "$(jq -r '.worker' "${SUBMITTY_INSTALL_DIR:?}/config/submitty.json")" == "true" ]] && echo 1 || echo 0)"
IS_VAGRANT="$([[ -d "${SUBMITTY_REPOSITORY:?}/.vagrant" ]] && echo 1 || echo 0)"
IS_UTM="$([[ -d "${SUBMITTY_REPOSITORY:?}/.utm" ]] && echo 1 || echo 0)"
IS_CI="$([[ -f "${SUBMITTY_REPOSITORY:?}/.github_actions_ci_flag" ]] && echo 1 || echo 0)"

# Because shellcheck is run with the python wrapper we need to disable the 'Not following' error
# shellcheck disable=SC1091
source "${SUBMITTY_REPOSITORY:?}/.setup/bin/versions.sh"

if [ "${IS_WORKER:?}" == 0 ]; then
    ALL_DAEMONS=( submitty_websocket_server submitty_autograding_shipper submitty_autograding_worker submitty_daemon_jobs_handler )
    RESTART_DAEMONS=( submitty_websocket_server submitty_daemon_jobs_handler )
else
    ALL_DAEMONS=( submitty_autograding_worker )
    RESTART_DAEMONS=( )
fi

#############################################################
# Re-Read other variables from submitty.json and submitty_users.json
# (eventually will remove these from the /usr/local/submitty/.setup/INSTALL_SUBMITTY.sh script)

SUBMITTY_DATA_DIR="$(jq -r '.submitty_data_dir' "${SUBMITTY_INSTALL_DIR:?}/config/submitty.json")"
# Worker does not need course builders so just use root
if [ "${IS_WORKER:?}" == 0 ]; then
    COURSE_BUILDERS_GROUP="$(jq -r '.course_builders_group' "${SUBMITTY_INSTALL_DIR:?}/config/submitty_users.json")"
else
    COURSE_BUILDERS_GROUP="root"
fi

NUM_UNTRUSTED="$(jq -r '.num_untrusted' "${SUBMITTY_INSTALL_DIR:?}/config/submitty_users.json")"
FIRST_UNTRUSTED_UID="$(jq -r '.first_untrusted_uid' "${SUBMITTY_INSTALL_DIR:?}/config/submitty_users.json")"
FIRST_UNTRUSTED_GID="$(jq -r '.first_untrusted_gid' "${SUBMITTY_INSTALL_DIR:?}/config/submitty_users.json")"
DAEMON_USER="$(jq -r '.daemon_user' "${SUBMITTY_INSTALL_DIR:?}/config/submitty_users.json")"
DAEMON_GROUP="${DAEMON_USER:?}"
DAEMON_UID="$(jq -r '.daemon_uid' "${SUBMITTY_INSTALL_DIR:?}/config/submitty_users.json")"
DAEMON_GID="$(jq -r '.daemon_gid' "${SUBMITTY_INSTALL_DIR:?}/config/submitty_users.json")"
PHP_USER="$(jq -r '.php_user' "${SUBMITTY_INSTALL_DIR:?}/config/submitty_users.json")"
CGI_USER="$(jq -r '.cgi_user' "${SUBMITTY_INSTALL_DIR:?}/config/submitty_users.json")"

# Worker does not have daemon PHP so just use daemon group
if [ "${IS_WORKER:?}" == 0 ]; then
    DAEMONPHP_GROUP="$(jq -r '.daemonphp_group' "${SUBMITTY_INSTALL_DIR:?}/config/submitty_users.json")"
else
    DAEMONPHP_GROUP="${DAEMON_GROUP:?}"
fi

DAEMONCGI_GROUP="$(jq -r '.daemoncgi_group' "${SUBMITTY_INSTALL_DIR:?}/config/submitty_users.json")"
DAEMONPHPCGI_GROUP="$(jq -r '.daemonphpcgi_group' "${SUBMITTY_INSTALL_DIR:?}/config/submitty_users.json")"
SUPERVISOR_USER="$(jq -r '.supervisor_user' "${SUBMITTY_INSTALL_DIR:?}/config/submitty_users.json")"

# This function takes a single argument, the name of the file to be edited
function replace_fillin_variables {
    sed -i -e "s|__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__|${SUBMITTY_INSTALL_DIR:?}|g" "$1"
    sed -i -e "s|__INSTALL__FILLIN__SUBMITTY_DATA_DIR__|${SUBMITTY_DATA_DIR:?}|g" "$1"

    sed -i -e "s|__INSTALL__FILLIN__NUM_UNTRUSTED__|${NUM_UNTRUSTED:?}|g" "$1"
    sed -i -e "s|__INSTALL__FILLIN__FIRST_UNTRUSTED_UID__|${FIRST_UNTRUSTED_UID:?}|g" "$1"
    sed -i -e "s|__INSTALL__FILLIN__FIRST_UNTRUSTED_GID__|${FIRST_UNTRUSTED_GID:?}|g" "$1"

    sed -i -e "s|__INSTALL__FILLIN__DAEMON_UID__|${DAEMON_UID:?}|g" "$1"
    sed -i -e "s|__INSTALL__FILLIN__DAEMON_GID__|${DAEMON_GID:?}|g" "$1"

    # FIXME: Add some error checking to make sure these values were filled in correctly
}

export IS_VAGRANT
export IS_UTM
export IS_CI
export SUBMITTY_REPOSITORY
export SUBMITTY_INSTALL_DIR
export IS_WORKER
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

export -f replace_fillin_variables
