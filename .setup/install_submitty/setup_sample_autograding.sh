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
########################################################################################################################
########################################################################################################################
# COPY THE SAMPLE FILES FOR COURSE MANAGEMENT

echo -e "Copy the sample files"

# copy the files from the repo
rsync -rtz "${SUBMITTY_REPOSITORY}/more_autograding_examples" "${SUBMITTY_INSTALL_DIR}"

# copy more_autograding_examples in order to make cypress autograding work
if [ "${VAGRANT}" == 1 ]; then 
    rsync -rtz "${SUBMITTY_REPOSITORY}/more_autograding_examples/" "${SUBMITTY_REPOSITORY}/site/cypress/fixtures/copy_of_more_autograding_examples/"
fi

# root will be owner & group of these files
chown -R  root:root "${SUBMITTY_INSTALL_DIR}/more_autograding_examples"
# but everyone can read all that files & directories, and cd into all the directories
find "${SUBMITTY_INSTALL_DIR}/more_autograding_examples" -type d -exec chmod 555 {} \;
find "${SUBMITTY_INSTALL_DIR}/more_autograding_examples" -type f -exec chmod 444 {} \;
########################################################################################################################
########################################################################################################################
# PREPARE THE UNTRUSTED_EXEUCTE EXECUTABLE WITH SUID

# copy the file
rsync -rtz  "${SUBMITTY_REPOSITORY}/.setup/untrusted_execute.c"   "${SUBMITTY_INSTALL_DIR}/.setup/"
# replace necessary variables
replace_fillin_variables "${SUBMITTY_INSTALL_DIR}/.setup/untrusted_execute.c"

# SUID (Set owner User ID up on execution), allows the $DAEMON_USER
# to run this executable as sudo/root, which is necessary for the
# "switch user" to untrusted as part of the sandbox.

pushd "${SUBMITTY_INSTALL_DIR}/.setup/" > /dev/null
# set ownership/permissions on the source code
chown root:root untrusted_execute.c
chmod 500 untrusted_execute.c
# compile the code
g++ -static untrusted_execute.c -o "${SUBMITTY_INSTALL_DIR}/sbin/untrusted_execute"
# change permissions & set suid: (must be root)
chown root           "${SUBMITTY_INSTALL_DIR}/sbin/untrusted_execute"
chgrp "$DAEMON_USER" "${SUBMITTY_INSTALL_DIR}/sbin/untrusted_execute"
chmod 4550           "${SUBMITTY_INSTALL_DIR}/sbin/untrusted_execute"
popd > /dev/null
