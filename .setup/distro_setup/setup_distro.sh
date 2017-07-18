#!/usr/bin/env bash

# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit
fi

SOURCE="${BASH_SOURCE[0]}"
CURRENT_DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"

DISTRO=$(lsb_release -i | sed -e "s/Distributor\ ID\:\t//g")
LOWER_DISTRO=$(echo ${DISTRO} | tr '[:upper:]' '[:lower:]')

if [ ! -d ${CURRENT_DIR}/${LOWER_DISTRO} ]; then
    (>&2 echo "Unknown distro: ${DISTRO}")
    exit 1
fi

echo "Setting up distro: ${DISTRO}"
${CURRENT_DIR}/${DISTRO}/setup_distro.sh
