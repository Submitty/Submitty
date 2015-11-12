#!/bin/bash

# Bash Script responsible for setting up Travis Environment. Keep definitions to common_env.sh,
# installation to setup.sh and then spinning up services to start.sh

BEFORE_SCRIPT_DIR=$(dirname $0)

source ${BEFORE_SCRIPT_DIR}/common/common_env.sh

source ${BEFORE_SCRIPT_DIR}/travis/setup.sh

source ${BEFORE_SCRIPT_DIR}/travis/start.sh