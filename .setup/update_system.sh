#!/usr/bin/env bash

# Usage:
#   update_system.sh

# print commands as we execute and fail early
set -ev

# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit 1
fi


# PATHS

#libraries for QR code processing:
#install DLL for zbar
apt-get install libzbar0 --yes

#libraries for comment counting :
#install cloc
apt-get install cloc --yes

##source ${CURRENT_DIR}/distro_setup/setup_distro.sh

echo "Done."
exit 0
