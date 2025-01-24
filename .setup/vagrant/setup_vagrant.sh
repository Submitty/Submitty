#!/usr/bin/env bash

# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit
fi

SUBMITTY_REPOSITORY=/usr/local/submitty/GIT_CHECKOUT/Submitty

# We only need to reset the system only if we've installed pip3,
# which only happens after we've installed the system
if [ -x "$(command -v pip3)" ]; then
    python3 ${SUBMITTY_REPOSITORY}/.setup/bin/reset_system.py
fi

CI=0
PARAMS=""
while (( "$#" )); do
  case "$1" in
    --ci)
      CI=1
      shift
      ;;
    *) # preserve positional arguments
      PARAMS="$PARAMS $1"
      shift
      ;;
  esac
done
# set positional arguments in their proper place
eval set -- "$PARAMS"
if [[ "$CI" -ne "0" ]] ; then
    echo "This file is used to check if Submitty is being run in the Github Actions CI." > ${SUBMITTY_REPOSITORY}/.github_actions_ci_flag
    echo "Submitty is being run in the Github Actions CI."
fi

# Expand the default logical volume for Ubuntu
lvresize -l +100%FREE /dev/mapper/ubuntu--vg-ubuntu--lv
resize2fs /dev/mapper/ubuntu--vg-ubuntu--lv

# Start installation
# shellcheck disable=2068
if ! sudo bash ${SUBMITTY_REPOSITORY}/.setup/install_system.sh --vagrant ${@}; then
    DISTRO=$(lsb_release -si | tr '[:upper:]' '[:lower:]')
    VERSION=$(lsb_release -sr | tr '[:upper:]' '[:lower:]')
    >&2 echo -e "
For whatever reason, Vagrant has failed to build. If reporting
an error to the developers, please be sure to also send the
build log of Vagrant located at:
.vagrant/logs/${DISTRO}-${VERSION}.log.
"
fi
