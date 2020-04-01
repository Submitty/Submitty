#!/usr/bin/env bash

################################################################################################################
################################################################################################################
# COPY THE 1.0 Grading Website

THIS_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null && pwd )"
CONF_DIR=${THIS_DIR}/../../../config

DEBUG_ENABLED=$(jq -r '.debugging_enabled' ${CONF_DIR}/database.json)

if [ ${DEBUG_ENABLED} = true ]; then
    echo -e "## USING DEVELOPMENTAL SITE INSTALLER ##\n\n"
    bash ${THIS_DIR}/install_submitty/install_site_dev.sh
else
    bash ${THIS_DIR}/install_submitty/install_site_prod.sh
fi
