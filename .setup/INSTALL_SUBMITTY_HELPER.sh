#!/usr/bin/env bash

echo -e "
\033[0;31m
#####################################################################
#                               WARNING
#
# Running either:
#   ${SUBMITTY_INSTALL_DIR}/.setup/INSTALL_SUBMITTY.sh or
#   ${SUBMITTY_REPOSITORY}/.setup/INSTALL_SUBMITTY_HELPER.sh
#
# is now deprecated and these scripts will be removed in a future
# version of Submitty. Instead, please run the following script from
# now on:
#   bash ${SUBMITTY_REPOSITORY}/.setup/install_submitty.sh
#
#####################################################################
\033[0m
"

THIS_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null && pwd )"
source ${THIS_DIR}/install_submitty.sh
