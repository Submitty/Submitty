#!/usr/bin/env bash

################################################################################################################
################################################################################################################
# COPY THE 1.0 Grading Website

THIS_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null && pwd )"
CONF_DIR=${THIS_DIR}/../../../config

DEBUG_ENABLED=$(jq -r '.debugging_enabled' ${CONF_DIR}/database.json)

if [[ "$#" -ge 1 && $1 == "skip_rsync" ]]; then
    bash ${THIS_DIR}/install_submitty/install_site.sh skip_rsync
else
    bash ${THIS_DIR}/install_submitty/install_site.sh
fi
