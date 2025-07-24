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

SUBMITTY_REPOSITORY="$(jq -r '.submitty_repository' "${SUBMITTY_CONFIG_DIR:?}/submitty.json")"
# shellcheck disable=SC1091
source "${SUBMITTY_REPOSITORY:?}/.setup/install_submitty/get_globals.sh"

cat << EOF
########################################################################################################################
########################################################################################################################
# SETTING UP DIRECTORIES
EOF

########################################################################################################################
########################################################################################################################
# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit 1
fi

# if the top level INSTALL directory does not exist, then make it
mkdir -p "${SUBMITTY_INSTALL_DIR:?}"


# option for clean install (delete all existing directories/files
if [[ "$#" -ge 1 && "$1" == "clean" ]] ; then

    # pop this argument from the list of arguments...
    shift

    echo -e "\nDeleting submitty installation directories, ${SUBMITTY_INSTALL_DIR:?}, for a clean installation\n"

    if [[ "$#" -ge 1 && $1 == "quick" ]] ; then
        # pop this argument from the list of arguments...
        shift

        rm -rf "${SUBMITTY_INSTALL_DIR:?}/site/app"
        rm -rf "${SUBMITTY_INSTALL_DIR:?}/site/cache"
        rm -rf "${SUBMITTY_INSTALL_DIR:?}/site/cgi-bin"
        rm -rf "${SUBMITTY_INSTALL_DIR:?}/site/config"
        rm -rf "${SUBMITTY_INSTALL_DIR:?}/site/cypress"
        rm -rf "${SUBMITTY_INSTALL_DIR:?}/site/public"
        rm -rf "${SUBMITTY_INSTALL_DIR:?}/site/room_templates"
        rm -rf "${SUBMITTY_INSTALL_DIR:?}/site/socket"
        rm -rf "${SUBMITTY_INSTALL_DIR:?}/site/tests"
        find "${SUBMITTY_INSTALL_DIR:?}/site" -maxdepth 1 -type f -exec rm {} \;
    else
        rm -rf "${SUBMITTY_INSTALL_DIR:?}/site"
        rm -rf "${SUBMITTY_INSTALL_DIR:?}/src"
        rm -rf "${SUBMITTY_INSTALL_DIR:?}/vendor"
        rm -rf "${SUBMITTY_INSTALL_DIR:?}/SubmittyAnalysisTools"
    fi
    rm -rf "${SUBMITTY_INSTALL_DIR:?}/bin"
    rm -rf "${SUBMITTY_INSTALL_DIR:?}/sbin"
    rm -rf "${SUBMITTY_INSTALL_DIR:?}/test_suite"
fi

# set the permissions of the top level directory
chown  "root:${COURSE_BUILDERS_GROUP:?}"  "${SUBMITTY_INSTALL_DIR}"
chmod  751                              "${SUBMITTY_INSTALL_DIR:?}"

########################################################################################################################
########################################################################################################################
# if the top level DATA, COURSES, & LOGS directores do not exist, then make them

echo -e "Make top level SUBMITTY DATA directories & set permissions"

mkdir -p "${SUBMITTY_DATA_DIR:?}"

if [ "${IS_WORKER:?}" == 1 ]; then
    echo -e "INSTALLING SUBMITTY IN IS_WORKER MODE"
else
    echo -e "INSTALLING PRIMARY SUBMITTY"
fi

#Make a courses and checkouts directory if not in worker mode.
if [ "${IS_WORKER:?}" == 0 ]; then
    mkdir -p "${SUBMITTY_DATA_DIR:?}/courses"
    mkdir -p "${SUBMITTY_DATA_DIR:?}/user_data"
    mkdir -p "${SUBMITTY_DATA_DIR:?}/vcs"
    mkdir -p "${SUBMITTY_DATA_DIR:?}/vcs/git"
fi


# ------------------------------------------------------------------------
# Create the logs directories that exist on both primary & worker machines
mkdir -p "${SUBMITTY_DATA_DIR:?}/logs"
mkdir -p "${SUBMITTY_DATA_DIR:?}/logs/autograding"
mkdir -p "${SUBMITTY_DATA_DIR:?}/logs/autograding_stack_traces"
# Create the logs directories that only exist on the primary machine
if [ "${IS_WORKER:?}" == 0 ]; then
    mkdir -p "${SUBMITTY_DATA_DIR:?}/logs/access"
    mkdir -p "${SUBMITTY_DATA_DIR:?}/logs/bulk_uploads"
    mkdir -p "${SUBMITTY_DATA_DIR:?}/logs/emails"
    mkdir -p "${SUBMITTY_DATA_DIR:?}/logs/notifications"
    mkdir -p "${SUBMITTY_DATA_DIR:?}/logs/site_errors"
    mkdir -p "${SUBMITTY_DATA_DIR:?}/logs/socket_errors"
    mkdir -p "${SUBMITTY_DATA_DIR:?}/logs/ta_grading"
    mkdir -p "${SUBMITTY_DATA_DIR:?}/logs/course_creation"
    mkdir -p "${SUBMITTY_DATA_DIR:?}/logs/vcs_generation"
    mkdir -p "${SUBMITTY_DATA_DIR:?}/logs/rainbow_grades"
    mkdir -p "${SUBMITTY_DATA_DIR:?}/logs/psql"
    mkdir -p "${SUBMITTY_DATA_DIR:?}/logs/preferred_names"
    mkdir -p "${SUBMITTY_DATA_DIR:?}/logs/office_hours_queue"
    mkdir -p "${SUBMITTY_DATA_DIR:?}/logs/docker"
    mkdir -p "${SUBMITTY_DATA_DIR:?}/logs/daemon_job_queue"
    mkdir -p "${SUBMITTY_DATA_DIR:?}/logs/sysinfo"
fi
# ------------------------------------------------------------------------

# set the permissions of these directories
chown  "root:${COURSE_BUILDERS_GROUP:?}"              "${SUBMITTY_DATA_DIR:?}"
chmod  751                                            "${SUBMITTY_DATA_DIR:?}"

#Set up courses and version control ownership if not in worker mode
if [ "${IS_WORKER:?}" == 0 ]; then
    chown  "root:${COURSE_BUILDERS_GROUP:?}"          "${SUBMITTY_DATA_DIR:?}/courses"
    chmod  751                                        "${SUBMITTY_DATA_DIR:?}/courses"
    chown  "${PHP_USER:?}:${PHP_USER:?}"              "${SUBMITTY_DATA_DIR:?}/user_data"
    chmod  770                                        "${SUBMITTY_DATA_DIR:?}/user_data"
    chown  "root:${DAEMONPHPCGI_GROUP:?}"             "${SUBMITTY_DATA_DIR:?}/vcs"
    chmod  770                                        "${SUBMITTY_DATA_DIR:?}/vcs"
    chown  "${CGI_USER:?}:${DAEMONPHPCGI_GROUP:?}"    "${SUBMITTY_DATA_DIR:?}/vcs/git"
    chmod  770                                        "${SUBMITTY_DATA_DIR:?}/vcs/git"
fi

# ------------------------------------------------------------------------
# Set owner/group of the top level logs directory
chown "root:${COURSE_BUILDERS_GROUP:?}"               "${SUBMITTY_DATA_DIR:?}/logs"
# Set owner/group for logs directories that exist on both primary & work machines
chown  -R "${DAEMON_USER:?}":"${DAEMONPHP_GROUP:?}"   "${SUBMITTY_DATA_DIR:?}/logs/autograding"
chown  -R "${DAEMON_USER:?}":"${DAEMONPHP_GROUP:?}"   "${SUBMITTY_DATA_DIR:?}/logs/autograding_stack_traces"

# Set owner/group for logs directories that exist only on the primary machine
if [ "${IS_WORKER:?}" == 0 ]; then
    chown  -R "${PHP_USER:?}":"${COURSE_BUILDERS_GROUP:?}"    "${SUBMITTY_DATA_DIR:?}/logs/access"
    chown  -R "${DAEMON_USER:?}":"${COURSE_BUILDERS_GROUP:?}" "${SUBMITTY_DATA_DIR:?}/logs/bulk_uploads"
    chown  -R "${DAEMON_USER:?}":"${COURSE_BUILDERS_GROUP:?}" "${SUBMITTY_DATA_DIR:?}/logs/emails"
    chown  -R "${DAEMON_USER:?}":"${COURSE_BUILDERS_GROUP:?}" "${SUBMITTY_DATA_DIR:?}/logs/notifications"
    chown  -R "${DAEMON_USER:?}":"${COURSE_BUILDERS_GROUP:?}" "${SUBMITTY_DATA_DIR:?}/logs/course_creation"
    chown  -R "${DAEMON_USER:?}":"${COURSE_BUILDERS_GROUP:?}" "${SUBMITTY_DATA_DIR:?}/logs/rainbow_grades"
    chown  -R "${PHP_USER:?}":"${COURSE_BUILDERS_GROUP:?}"    "${SUBMITTY_DATA_DIR:?}/logs/site_errors"
    chown  -R "${PHP_USER:?}":"${COURSE_BUILDERS_GROUP:?}"    "${SUBMITTY_DATA_DIR:?}/logs/socket_errors"
    chown  -R "${PHP_USER:?}":"${COURSE_BUILDERS_GROUP:?}"    "${SUBMITTY_DATA_DIR:?}/logs/ta_grading"
    chown  -R "${DAEMON_USER:?}":"${COURSE_BUILDERS_GROUP:?}" "${SUBMITTY_DATA_DIR:?}/logs/vcs_generation"
    chown  -R postgres:"${DAEMON_GROUP:?}"                    "${SUBMITTY_DATA_DIR:?}/logs/psql"

    # Folder g+w permission needed to permit DAEMON_GROUP to remove expired Postgresql logs.
    chmod  g+w                                                "${SUBMITTY_DATA_DIR:?}/logs/psql"
    chown  -R "${DAEMON_USER:?}:${DAEMON_GROUP:?}"            "${SUBMITTY_DATA_DIR:?}/logs/preferred_names"
    chown  -R "${PHP_USER:?}:${COURSE_BUILDERS_GROUP:?}"      "${SUBMITTY_DATA_DIR:?}/logs/office_hours_queue"
    chown  -R "${DAEMON_USER:?}:${DAEMONPHP_GROUP:?}"         "${SUBMITTY_DATA_DIR:?}/logs/docker"
    chown  -R "${DAEMON_USER:?}:${DAEMONPHP_GROUP:?}"         "${SUBMITTY_DATA_DIR:?}/logs/daemon_job_queue"
    chown  -R "${DAEMON_USER:?}:${DAEMONPHP_GROUP:?}"         "${SUBMITTY_DATA_DIR:?}/logs/sysinfo"

    # php & daemon needs to be able to read workers & containers config
    chown  "${PHP_USER:?}:${DAEMONPHP_GROUP:?}"               "${SUBMITTY_INSTALL_DIR:?}/config/autograding_workers.json"
    chown  "${PHP_USER:?}:${DAEMONPHP_GROUP:?}"               "${SUBMITTY_INSTALL_DIR:?}/config/autograding_containers.json"
fi

# Set permissions of all files in the logs directories
find "${SUBMITTY_DATA_DIR:?}/logs/" -type f -exec chmod 640 {} \;
# Set permissions of all logs subdirectires
find "${SUBMITTY_DATA_DIR:?}/logs/" -mindepth 1 -type d -exec chmod 750 {} \;
# Created files in the logs subdirectories should inherit the group of the parent directory
find "${SUBMITTY_DATA_DIR:?}/logs/" -mindepth 1 -type d -exec chmod g+s {} \;
# Set permissions of the top level logs directory
chmod 751 "${SUBMITTY_DATA_DIR:?}/logs/"

# ------------------------------------------------------------------------


#Set up shipper grading directories if not in worker mode.
if [ "${IS_WORKER:?}" == 0 ]; then
    # if the to_be_graded directories do not exist, then make them
    mkdir -p "${SUBMITTY_DATA_DIR:?}/to_be_graded_queue"
    mkdir -p "${SUBMITTY_DATA_DIR:?}/daemon_job_queue"
    mkdir -p "${SUBMITTY_DATA_DIR:?}/in_progress_grading"

    # set the permissions of these directories
    # INTERACTIVE QUEUE: the PHP_USER will write items to this list, DAEMON_USER will remove them
    # BATCH QUEUE: course builders (instructors & head TAs) will write items to this list, DAEMON_USER will remove them
    chown  "${DAEMON_USER:?}:${DAEMONPHP_GROUP:?}"    "${SUBMITTY_DATA_DIR:?}/to_be_graded_queue"
    chmod  770                                        "${SUBMITTY_DATA_DIR:?}/to_be_graded_queue"
    chown  "${DAEMON_USER:?}:${DAEMONPHP_GROUP:?}"    "${SUBMITTY_DATA_DIR:?}/daemon_job_queue"
    chmod  770                                        "${SUBMITTY_DATA_DIR:?}/daemon_job_queue"
    chown  "${DAEMON_USER:?}:${DAEMONPHP_GROUP:?}"    "${SUBMITTY_DATA_DIR:?}/in_progress_grading"
    chmod  750                                        "${SUBMITTY_DATA_DIR:?}/in_progress_grading"
fi


# tmp folder
mkdir -p        "${SUBMITTY_DATA_DIR:?}/tmp"
chown root:root "${SUBMITTY_DATA_DIR:?}/tmp"
chmod 511       "${SUBMITTY_DATA_DIR:?}/tmp"
