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
# COPY THE CORE GRADING CODE (C++ files) & BUILD THE SUBMITTY GRADING LIBRARY

# copy the files from the repo
rsync -rtz "${SUBMITTY_REPOSITORY}/grading" "${SUBMITTY_INSTALL_DIR}/src"

# copy the allowed_autograding_commands_default.json to config
rsync -tz "${SUBMITTY_REPOSITORY}/grading/allowed_autograding_commands_default.json" "${SUBMITTY_INSTALL_DIR}/config"

# replace filling variables
sed -i -e "s|__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__|$SUBMITTY_INSTALL_DIR|g" "${SUBMITTY_INSTALL_DIR}/config/allowed_autograding_commands_default.json"

# # change permissions of allowed_autograding_commands_default.json
chown "root":"root" "${SUBMITTY_INSTALL_DIR}/config/allowed_autograding_commands_default.json"
chmod 644 "${SUBMITTY_INSTALL_DIR}/config/allowed_autograding_commands_default.json"

# create allowed_autograding_commands_custom.json if doesnt exist
if [[ ! -e "${SUBMITTY_INSTALL_DIR}/config/allowed_autograding_commands_custom.json" ]]; then
    rsync -tz "${SUBMITTY_REPOSITORY}/grading/allowed_autograding_commands_custom.json" "${SUBMITTY_INSTALL_DIR}/config"
fi

# replace filling variables
sed -i -e "s|__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__|$SUBMITTY_INSTALL_DIR|g" "${SUBMITTY_INSTALL_DIR}/config/allowed_autograding_commands_custom.json"

# # change permissions of allowed_autograding_commands_custom.json
chown "root":"root" "${SUBMITTY_INSTALL_DIR}/config/allowed_autograding_commands_custom.json"
chmod 644 "${SUBMITTY_INSTALL_DIR}/config/allowed_autograding_commands_custom.json"

#replace necessary variables
array=( Sample_CMakeLists.txt CMakeLists.txt system_call_check.cpp seccomp_functions.cpp execute.cpp load_config_json.cpp )
for i in "${array[@]}"; do
    replace_fillin_variables "${SUBMITTY_INSTALL_DIR}/src/grading/${i}"
done

# building the autograding library
mkdir -p "${SUBMITTY_INSTALL_DIR}/src/grading/lib"
pushd "${SUBMITTY_INSTALL_DIR}/src/grading/lib"
cmake ..
if ! make; then
    echo "ERROR BUILDING AUTOGRADING LIBRARY"
    exit 1
fi
popd > /dev/null

# root will be owner & group of these files
chown -R  root:root "${SUBMITTY_INSTALL_DIR}/src"
# "other" can cd into & ls all subdirectories
find "${SUBMITTY_INSTALL_DIR}/src" -type d -exec chmod 555 {} \;
# "other" can read all files
find "${SUBMITTY_INSTALL_DIR}/src" -type f -exec chmod 444 {} \;

chgrp submitty_daemon "${SUBMITTY_INSTALL_DIR}/src/grading/python/submitty_router.py"
chmod g+wrx           "${SUBMITTY_INSTALL_DIR}/src/grading/python/submitty_router.py"
