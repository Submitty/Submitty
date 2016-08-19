#!/usr/bin/env bash

echo "Setting up auto-grader test suite"

SUBMITTY_INSTALL_DIR=/usr/local/submitty
SUBMITTY_DATA_DIR=/var/local/submitty


mkdir -p ${SUBMITTY_INSTALL_DIR}/test_suite
mkdir -p ${SUBMITTY_INSTALL_DIR}/test_suite/log
cp -r tests/. ${SUBMITTY_INSTALL_DIR}/test_suite

sed -i -e "s|__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__|${SUBMITTY_INSTALL_DIR}|g" ${SUBMITTY_INSTALL_DIR}/test_suite/integrationTests/lib.py

chmod -R 777 ${SUBMITTY_INSTALL_DIR}