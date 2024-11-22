#!/usr/bin/env bash

set -e

THIS_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null && pwd )"
bash "${THIS_DIR}/install_submitty/install_site.sh"
