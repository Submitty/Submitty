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

SUBMITTY_REPOSITORY=$(jq -r '.submitty_repository' ${SUBMITTY_CONFIG_DIR:?}/submitty.json)
source ${SUBMITTY_REPOSITORY:?}/.setup/install_submitty/get_globals.sh "config=${SUBMITTY_CONFIG_DIR:?}"
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
