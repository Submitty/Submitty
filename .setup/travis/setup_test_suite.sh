#!/usr/bin/env bash

echo "Setting up auto-grader test suite"

SUBMITTY_INSTALL_DIR=/usr/local/submitty
SUBMITTY_DATA_DIR=/var/local/submitty

mkdir -p ${SUBMITTY_INSTALL_DIR}
mkdir -p ${SUBMITTY_DATA_DIR}
mkdir -p ${SUBMITTY_INSTALL_DIR}/src
mkdir -p ${SUBMITTY_INSTALL_DIR}/test_suite
mkdir -p ${SUBMITTY_INSTALL_DIR}/test_suite/log
cp -r tests/. ${SUBMITTY_INSTALL_DIR}/test_suite
cp -r sample_files ${SUBMITTY_INSTALL_DIR}/sample_files
cp -r grading/ ${SUBMITTY_INSTALL_DIR}/src/

sed -i -e "s|__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__|${SUBMITTY_INSTALL_DIR}|g" ${SUBMITTY_INSTALL_DIR}/test_suite/integrationTests/lib.py
sed -i -e "s|__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__|${SUBMITTY_INSTALL_DIR}|g" ${SUBMITTY_INSTALL_DIR}/src/grading/system_call_check.cpp
sed -i -e "s|__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__|${SUBMITTY_INSTALL_DIR}|g" ${SUBMITTY_INSTALL_DIR}/src/grading/execute.cpp
sed -i -e "s|__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__|${SUBMITTY_INSTALL_DIR}|g" ${SUBMITTY_INSTALL_DIR}/src/grading/Sample_CMakeLists.txt
sed -i -e "s|__INSTALL__FILLIN__SUBMITTY_DATA_DIR__|${SUBMITTY_DATA_DIR}|g" ${SUBMITTY_INSTALL_DIR}/src/grading/Sample_CMakeLists.txt
sed -i -e "s|__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__|${SUBMITTY_INSTALL_DIR}|g" ${SUBMITTY_INSTALL_DIR}/src/grading/CMakeLists.txt
sed -i -e "s|__INSTALL__FILLIN__SUBMITTY_DATA_DIR__|${SUBMITTY_DATA_DIR}|g" ${SUBMITTY_INSTALL_DIR}/src/grading/CMakeLists.txt

# building the autograding library
mkdir -p ${SUBMITTY_INSTALL_DIR}/src/grading/lib
pushd ${SUBMITTY_INSTALL_DIR}/src/grading/lib
cmake ..
make
popd

chmod -R 777 ${SUBMITTY_INSTALL_DIR}