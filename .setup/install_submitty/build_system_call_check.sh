#!/usr/bin/env bash
set -ev

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

SUBMITTY_REPOSITORY=$(jq -r '.submitty_repository' "${SUBMITTY_CONFIG_DIR:?}/submitty.json")
# shellcheck disable=SC1091
source "${SUBMITTY_REPOSITORY:?}/.setup/install_submitty/get_globals.sh" "config=${SUBMITTY_CONFIG_DIR:?}"
# build the helper program for strace output and restrictions by system call categories
g++ "${SUBMITTY_INSTALL_DIR}/src/grading/system_call_check.cpp" -o "${SUBMITTY_INSTALL_DIR}/bin/system_call_check.out"

# build the helper program for calculating early submission incentive extensions
g++ "${SUBMITTY_INSTALL_DIR}/bin/calculate_extensions.cpp" -lboost_system -lboost_filesystem -std=c++11 -Wall -g -o "${SUBMITTY_INSTALL_DIR}/bin/calculate_extensions.out"

GRADINGCODE="${SUBMITTY_INSTALL_DIR}/src/grading"
JSONCODE="${SUBMITTY_INSTALL_DIR}/vendor/include"

# Create the complete/build config using main_configure
g++ "${GRADINGCODE}/main_configure.cpp" \
    "${GRADINGCODE}/load_config_json.cpp" \
    "${GRADINGCODE}/execute.cpp" \
    "${GRADINGCODE}/TestCase.cpp" \
    "${GRADINGCODE}/error_message.cpp" \
    "${GRADINGCODE}/window_utils.cpp" \
    "${GRADINGCODE}/dispatch.cpp" \
    "${GRADINGCODE}/change.cpp" \
    "${GRADINGCODE}/difference.cpp" \
    "${GRADINGCODE}/tokenSearch.cpp" \
    "${GRADINGCODE}/tokens.cpp" \
    "${GRADINGCODE}/clean.cpp" \
    "${GRADINGCODE}/execute_limits.cpp" \
    "${GRADINGCODE}/seccomp_functions.cpp" \
    "${GRADINGCODE}/empty_custom_function.cpp" \
    "${GRADINGCODE}/allowed_autograding_commands.cpp" \
    "-I${JSONCODE}" \
    -pthread -std=c++11 -lseccomp -o "${SUBMITTY_INSTALL_DIR}/bin/configure.out"

# set the permissions
chown "root:${COURSE_BUILDERS_GROUP}" "${SUBMITTY_INSTALL_DIR}/bin/system_call_check.out"
chmod 550                             "${SUBMITTY_INSTALL_DIR}/bin/system_call_check.out"

chown "root:${COURSE_BUILDERS_GROUP}" "${SUBMITTY_INSTALL_DIR}/bin/calculate_extensions.out"
chmod 550                             "${SUBMITTY_INSTALL_DIR}/bin/calculate_extensions.out"

chown "${DAEMON_USER}:${COURSE_BUILDERS_GROUP}" "${SUBMITTY_INSTALL_DIR}/bin/configure.out"
chmod 550 "${SUBMITTY_INSTALL_DIR}/bin/configure.out"
