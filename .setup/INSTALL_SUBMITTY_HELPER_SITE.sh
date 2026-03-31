#!/usr/bin/env bash

THIS_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null && pwd )"
SUBMITTY_CONFIG_DIR="/usr/local/submitty/config"

if [[ "$1" == "--full" ]]; then
    bash "${THIS_DIR}/install_submitty/install_site.sh" browscap "config=${SUBMITTY_CONFIG_DIR:?}"
else 
	bash "${THIS_DIR}/install_submitty/copy_site.sh" browscap "config=${SUBMITTY_CONFIG_DIR:?}"
fi
