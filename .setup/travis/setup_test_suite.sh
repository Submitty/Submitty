#!/bin/bash

SOURCE="${BASH_SOURCE[0]}"
# resolve $SOURCE until the file is no longer a symlink
while [ -h "$SOURCE" ]; do
  DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"
  SOURCE="$(readlink "$SOURCE")"
  # if $SOURCE was a relative symlink, we need to resolve
  # it relative to the path where the symlink file was located
  [[ ${SOURCE} != /* ]] && SOURCE="$DIR/$SOURCE"
done
DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"

source ${DIR}/../common/common_env.sh

# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit 1
fi

set -ev

echo "Setting up auto-grader test suite"

mkdir -p ${SUBMITTY_INSTALL_DIR}/test_suite
mkdir -p ${SUBMITTY_INSTALL_DIR}/test_suite/log
cp -r tests/. ${SUBMITTY_INSTALL_DIR}/test_suite

echo "trying to debug " ${PHP_USER} ${CGI_USER}

sed -i -e "s|__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__|${SUBMITTY_INSTALL_DIR}|g" ${SUBMITTY_INSTALL_DIR}/test_suite/integrationTests/lib.py
