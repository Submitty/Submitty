#!/bin/bash

# Bash Script responsible for setting up Travis Environment. Keep definitions to common_env.sh,
# installation to setup.sh and then spinning up services to start.sh

#if [[ "$TRAVIS_BRANCH" = "master" ]]; then
#    RUN_E2E=true
#fi

BEFORE_SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

source ${BEFORE_SCRIPT_DIR}/common/common_env.sh

source ${BEFORE_SCRIPT_DIR}/travis/setup.sh

source ${BEFORE_SCRIPT_DIR}/travis/start.sh