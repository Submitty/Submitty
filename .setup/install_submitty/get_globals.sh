#!/usr/bin/env bash
set -ve

# First, we determine SUBMITTY_REPOSITORY from BASH_SOURCE, as this
# script is run from within GIT_CHECKOUT/Submitty. We use readlink to
# get an absolute path from a relative path here.
SUBMITTY_REPOSITORY="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null && pwd )/../.."
SUBMITTY_REPOSITORY="$(readlink -ne "$SUBMITTY_REPOSITORY")"
echo "SUBMITTY_REPOSITORY=${SUBMITTY_REPOSITORY}" >&2

SUBMITTY_INSTALL_DIR="$(readlink -ne "${SUBMITTY_REPOSITORY}/../..")"
SUBMITTY_INSTALL_DIR="$(readlink -ne "$SUBMITTY_INSTALL_DIR")"
echo "INSTALL_DIR=${SUBMITTY_REPOSITORY}" >&2

JSON_SUBMITTY_INSTALL_DIR="$(jq -r '.submitty_install_dir' "${SUBMITTY_INSTALL_DIR}/config/submitty.json")"
JSON_SUBMITTY_REPOSITORY="$(jq -r '.submitty_repository' "${SUBMITTY_INSTALL_DIR}/config/submitty.json")"

# Check that install dir in submitty.json matches
if [[ "$SUBMITTY_INSTALL_DIR" != "$JSON_SUBMITTY_INSTALL_DIR" ]]; then
    echo "SUBMITTY_INSTALL_DIR does not match submitty.config!" >&2
    echo "JSON_SUBMITTY_INSTALL_DIR=$JSON_SUBMITTY_INSTALL_DIR" >&2
    echo "Exiting..." >&2
    exit 1
fi

# Check that Submitty repo dir in submitty.json matches
if [[ "$SUBMITTY_REPOSITORY" != "$JSON_SUBMITTY_REPOSITORY" ]]; then
    echo "SUBMITTY_REPOSITORY does not match submitty.config!" >&2
    echo "JSON_SUBMITTY_REPOSITORY=$JSON_SUBMITTY_REPOSITORY" >&2
    echo "Exiting..." >&2
    exit 1
fi

IS_WORKER="$([[ "$(jq -r '.worker' "${SUBMITTY_INSTALL_DIR}/config/submitty.json")" == "true" ]] && echo 1 || echo 0)"
IS_VAGRANT="$([[ -d "${SUBMITTY_REPOSITORY}/.vagrant" ]] && echo 1 || echo 0)"
IS_UTM="$([[ -d "${SUBMITTY_REPOSITORY}/.utm" ]] && echo 1 || echo 0)"
IS_CI="$([[ -f "${SUBMITTY_REPOSITORY}/.github_actions_ci_flag" ]] && echo 1 || echo 0)"

# Because shellcheck is run with the python wrapper we need to disable the 'Not following' error
# shellcheck disable=SC1091
source "${SUBMITTY_REPOSITORY}/.setup/bin/versions.sh"

if [ "${IS_WORKER}" == 0 ]; then
    ALL_DAEMONS=( submitty_websocket_server submitty_autograding_shipper submitty_autograding_worker submitty_daemon_jobs_handler )
    RESTART_DAEMONS=( submitty_websocket_server submitty_daemon_jobs_handler )
else
    ALL_DAEMONS=( submitty_autograding_worker )
    RESTART_DAEMONS=( )
fi

#############################################################
# Re-Read other variables from submitty.json and submitty_users.json
# (eventually will remove these from the /usr/local/submitty/.setup/INSTALL_SUBMITTY.sh script)

SUBMITTY_DATA_DIR="$(jq -r '.submitty_data_dir' "${SUBMITTY_INSTALL_DIR}/config/submitty.json")"
# Worker does not need course builders so just use root
if [ "${IS_WORKER}" == 0 ]; then
    COURSE_BUILDERS_GROUP="$(jq -r '.course_builders_group' "${SUBMITTY_INSTALL_DIR}/config/submitty_users.json")"
else
    COURSE_BUILDERS_GROUP=root
fi

NUM_UNTRUSTED="$(jq -r '.num_untrusted' "${SUBMITTY_INSTALL_DIR}/config/submitty_users.json")"
FIRST_UNTRUSTED_UID="$(jq -r '.first_untrusted_uid' "${SUBMITTY_INSTALL_DIR}/config/submitty_users.json")"
FIRST_UNTRUSTED_GID="$(jq -r '.first_untrusted_gid' "${SUBMITTY_INSTALL_DIR}/config/submitty_users.json")"
DAEMON_USER="$(jq -r '.daemon_user' "${SUBMITTY_INSTALL_DIR}/config/submitty_users.json")"
DAEMON_GROUP="${DAEMON_USER}"
DAEMON_UID="$(jq -r '.daemon_uid' "${SUBMITTY_INSTALL_DIR}/config/submitty_users.json")"
DAEMON_GID="$(jq -r '.daemon_gid' "${SUBMITTY_INSTALL_DIR}/config/submitty_users.json")"
PHP_USER="$(jq -r '.php_user' "${SUBMITTY_INSTALL_DIR}/config/submitty_users.json")"
CGI_USER="$(jq -r '.cgi_user' "${SUBMITTY_INSTALL_DIR}/config/submitty_users.json")"

# Worker does not have daemon PHP so just use daemon group
if [ "${IS_WORKER}" == 0 ]; then
    DAEMONPHP_GROUP="$(jq -r '.daemonphp_group' "${SUBMITTY_INSTALL_DIR}/config/submitty_users.json")"
else
    DAEMONPHP_GROUP="${DAEMON_GROUP}"
fi

DAEMONCGI_GROUP="$(jq -r '.daemoncgi_group' "${SUBMITTY_INSTALL_DIR}/config/submitty_users.json")"
DAEMONPHPCGI_GROUP="$(jq -r '.daemonphpcgi_group' "${SUBMITTY_INSTALL_DIR}/config/submitty_users.json")"
SUPERVISOR_USER="$(jq -r '.supervisor_user' "${SUBMITTY_INSTALL_DIR}/config/submitty_users.json")"

# This function takes a single argument, the name of the file to be edited
function replace_fillin_variables {
    sed -i -e "s|__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__|$SUBMITTY_INSTALL_DIR|g" "$1"
    sed -i -e "s|__INSTALL__FILLIN__SUBMITTY_DATA_DIR__|$SUBMITTY_DATA_DIR|g" "$1"

    sed -i -e "s|__INSTALL__FILLIN__NUM_UNTRUSTED__|$NUM_UNTRUSTED|g" "$1"
    sed -i -e "s|__INSTALL__FILLIN__FIRST_UNTRUSTED_UID__|$FIRST_UNTRUSTED_UID|g" "$1"
    sed -i -e "s|__INSTALL__FILLIN__FIRST_UNTRUSTED_GID__|$FIRST_UNTRUSTED_GID|g" "$1"

    sed -i -e "s|__INSTALL__FILLIN__DAEMON_UID__|$DAEMON_UID|g" "$1"
    sed -i -e "s|__INSTALL__FILLIN__DAEMON_GID__|$DAEMON_GID|g" "$1"

    # FIXME: Add some error checking to make sure these values were filled in correctly
}

function export_and_print {
    echo "export $1=${!1}"
    export "$1"
}

set +v

export_and_print IS_VAGRANT
export_and_print IS_UTM
export_and_print IS_CI
export_and_print SUBMITTY_REPOSITORY
export_and_print SUBMITTY_INSTALL_DIR
export_and_print IS_WORKER
export_and_print ALL_DAEMONS
export_and_print RESTART_DAEMONS

export_and_print SUBMITTY_DATA_DIR
export_and_print COURSE_BUILDERS_GROUP
export_and_print NUM_UNTRUSTED
export_and_print FIRST_UNTRUSTED_UID
export_and_print FIRST_UNTRUSTED_GID
export_and_print DAEMON_USER
export_and_print DAEMON_GROUP
export_and_print DAEMON_UID
export_and_print DAEMON_GID
export_and_print PHP_USER
export_and_print CGI_USER
export_and_print DAEMONPHP_GROUP
export_and_print DAEMONCGI_GROUP
export_and_print DAEMONPHPCGI_GROUP
export_and_print SUPERVISOR_USER

set -v

export -f replace_fillin_variables
