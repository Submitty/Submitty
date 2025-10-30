#!/usr/bin/env bash

THIS_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null && pwd )"
SUBMITTY_CONFIG_DIR="/usr/local/submitty/config"
bash "${THIS_DIR}/install_submitty/install_bin.sh" browscap "config=${SUBMITTY_CONFIG_DIR:?}"
