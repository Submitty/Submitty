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
#################################################################
# DRMEMORY SETUP
#################

# Dr Memory is a tool for detecting memory errors in C++ programs (similar to Valgrind)

# FIXME: Use of this tool should eventually be moved to containerized
# autograding and not installed on the native primary and worker
# machines by default

# FIXME: DrMemory is initially installed in install_system.sh
# It is re-installed here (on every Submitty software update) in case of version updates.

pushd /tmp > /dev/null

echo "Updating DrMemory..."

rm -rf /tmp/DrMemory*
wget "https://github.com/DynamoRIO/drmemory/releases/download/${DRMEMORY_TAG}/DrMemory-Linux-${DRMEMORY_VERSION}.tar.gz" -o /dev/null > /dev/null 2>&1
tar -xpzf "DrMemory-Linux-${DRMEMORY_VERSION}.tar.gz"
rsync --delete -a "/tmp/DrMemory-Linux-${DRMEMORY_VERSION}/" "${SUBMITTY_INSTALL_DIR}/drmemory"
rm -rf /tmp/DrMemory*

chown -R "root:${COURSE_BUILDERS_GROUP}" "${SUBMITTY_INSTALL_DIR}/drmemory"
chmod -R 755 "${SUBMITTY_INSTALL_DIR}/drmemory"



echo "...DrMemory ${DRMEMORY_TAG} update complete."

popd > /dev/null
