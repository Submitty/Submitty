#!/usr/bin/env bash
#
# Tear down the Multipass proof-of-concept dev VM created by up.sh.
#
# Usage:
#   ./multipass/down.sh

set -euo pipefail

INSTANCE_NAME="${SUBMITTY_VM_NAME:-submitty-dev}"

if ! command -v multipass >/dev/null 2>&1; then
    echo "ERROR: multipass is not installed." >&2
    exit 1
fi

if ! multipass info "${INSTANCE_NAME}" >/dev/null 2>&1; then
    echo "No instance named '${INSTANCE_NAME}' found. Nothing to do."
    exit 0
fi

echo "==> Deleting and purging ${INSTANCE_NAME}"
multipass delete --purge "${INSTANCE_NAME}"
echo "==> Done."
