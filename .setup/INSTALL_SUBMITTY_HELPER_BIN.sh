#!/usr/bin/env bash

echo -e "
\033[0;31m
#####################################################################
#                               WARNING
#
# Using this script is deprecated. Please update your tooling to use
# .setup/install_submitty/install_bin.sh script. This script will be
# removed in a future version of Submitty.
#
#####################################################################
\033[0m
"

THIS_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null && pwd )"
source ${THIS_DIR}/install_submitty/install_bin.sh
