#!/usr/bin/env bash

set -euo pipefail

SCRIPT_PATH=".setup/install_submitty/install_site.sh"

if [ ! -f "${SCRIPT_PATH}" ]; then
    echo "ERROR: ${SCRIPT_PATH} not found"
    exit 1
fi

trigger_block=$(awk '
    /# BEGIN_FRONTEND_BUILD_TRIGGER_PATHS/ { in_block=1; next }
    /# END_FRONTEND_BUILD_TRIGGER_PATHS/ { in_block=0 }
    in_block { print }
' "${SCRIPT_PATH}")

if [ -z "${trigger_block}" ]; then
    echo "ERROR: Frontend trigger block markers not found or empty in ${SCRIPT_PATH}"
    exit 1
fi

required_paths=(
    '"site/ts/*"'
    '"site/vue/*"'
    '"site/package.json"'
    '"site/package-lock.json"'
    '"site/vite.config.js"'
    '"site/vite.config.mjs"'
    '"site/vite.config.ts"'
    '"site/.build.js"'
    '"site/tsconfig.json"'
)

missing=0
for required_path in "${required_paths[@]}"; do
    if ! printf '%s\n' "${trigger_block}" | grep -F -q -- "${required_path}"; then
        echo "ERROR: Missing required frontend trigger path ${required_path} in ${SCRIPT_PATH}"
        missing=1
    fi
done

if [ ${missing} -ne 0 ]; then
    exit 1
fi

echo "Frontend build trigger path check passed"