#!/usr/bin/env bash


# This function is from https://github.com/travis-ci/travis-build/blob/master/lib/travis/build/bash/travis_retry.bash
ANSI_RED="\033[31;1m"
ANSI_RESET="\033[0m"

wget_retry() {
    local result=0
    local count=1
    while [[ "${count}" -le 3 ]]; do
        [[ "${result}" -ne 0 ]] && {
            echo -e "\\n${ANSI_RED}The command \"${*}\" failed. Retrying, ${count} of 3.${ANSI_RESET}\\n" >&2
        }
        "${@}" && { result=0 && break; } || result="${?}"
        count="$((count + 1))"
        sleep 1
    done

    [[ "${count}" -gt 3 ]] && {
        echo -e "\\n${ANSI_RED}The command \"${*}\" failed 3 times.${ANSI_RESET}\\n" >&2
    }

    return "${result}"
}

# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit 1
fi

set -ev

echo "Setting up auto-grader test suite"


mkdir -p ${SUBMITTY_INSTALL_DIR}
mkdir -p ${SUBMITTY_DATA_DIR}
mkdir -p ${SUBMITTY_INSTALL_DIR}/src
cp -r grading/ ${SUBMITTY_INSTALL_DIR}/src/

# --------------------------------------
echo "Getting DrMemory..."
mkdir -p ${SUBMITTY_INSTALL_DIR}/DrMemory
pushd /tmp
DRMEM_TAG=release_2.0.1
DRMEM_VER=2.0.1-2
wget_retry wget https://github.com/DynamoRIO/drmemory/releases/download/${DRMEM_TAG}/DrMemory-Linux-${DRMEM_VER}.tar.gz
tar -xpzf DrMemory-Linux-${DRMEM_VER}.tar.gz -C ${SUBMITTY_INSTALL_DIR}/DrMemory
ln -s ${SUBMITTY_INSTALL_DIR}/DrMemory/DrMemory-Linux-${DRMEM_VER} ${SUBMITTY_INSTALL_DIR}/drmemory
rm DrMemory-Linux-${DRMEM_VER}.tar.gz
popd

# --------------------------------------
pushd /tmp

echo "Getting TCLAPP"
wget_retry wget https://sourceforge.net/projects/tclap/files/tclap-1.2.2.tar.gz -o /dev/null > /dev/null 2>&1
tar -xpzf tclap-1.2.2.tar.gz
rm /tmp/tclap-1.2.2.tar.gz
cd tclap-1.2.2/
sed -i 's/SUBDIRS = include examples docs tests msc config/SUBDIRS = include docs msc config/' Makefile.in
bash configure
make
make install
cd /tmp
rm -rf /tmp/tclap-1.2.2

popd > /dev/null


# --------------------------------------
echo "Getting JUnit..."
mkdir -p ${SUBMITTY_INSTALL_DIR}/java_tools/JUnit
mkdir -p ${SUBMITTY_INSTALL_DIR}/java_tools/hamcrest
mkdir -p ${SUBMITTY_INSTALL_DIR}/java_tools/jacoco
chmod -R 751 ${SUBMITTY_INSTALL_DIR}/java_tools/

JUNIT_VER=4.12
HAMCREST_VER=1.3

pushd ${SUBMITTY_INSTALL_DIR}/java_tools/JUnit
wget_retry wget https://maven-central.storage-download.googleapis.com/repos/central/data/junit/junit/${JUNIT_VER}/junit-${JUNIT_VER}.jar
chmod o+r . *.jar
popd

pushd ${SUBMITTY_INSTALL_DIR}/java_tools/hamcrest
wget_retry wget https://maven-central.storage-download.googleapis.com/repos/central/data/org/hamcrest/hamcrest-core/${HAMCREST_VER}/hamcrest-core-${HAMCREST_VER}.jar
chmod o+r . *.jar
popd

echo "Getting JaCoCo..."
JACOCO_VER=0.8.0
pushd ${SUBMITTY_INSTALL_DIR}/java_tools/jacoco
wget_retry wget https://github.com/jacoco/jacoco/releases/download/v${JACOCO_VER}/jacoco-${JACOCO_VER}.zip
mkdir jacoco-${JACOCO_VER}
unzip jacoco-${JACOCO_VER}.zip -d jacoco-${JACOCO_VER} > /dev/null
mv jacoco-${JACOCO_VER}/lib/jacococli.jar jacococli.jar
mv jacoco-${JACOCO_VER}/lib/jacocoagent.jar jacocoagent.jar
rm -rf jacoco-${JACOCO_VER}
rm jacoco-${JACOCO_VER}.zip
chmod o+r . *.jar
popd


# --------------------------------------
echo -e "Build the junit test runner"

# copy the file from the repo
mkdir -p $SUBMITTY_INSTALL_DIR/java_tools/JUnit/
cp junit_test_runner/TestRunner.java $SUBMITTY_INSTALL_DIR/java_tools/JUnit/TestRunner.java

pushd $SUBMITTY_INSTALL_DIR/java_tools/JUnit
# root will be owner & group of the source file
chown  root:root  TestRunner.java
# everyone can read this file
chmod  444 TestRunner.java

# compile the executable using the javac we use in the execute.cpp safelist
/usr/bin/javac -cp ./junit-4.12.jar TestRunner.java

# everyone can read the compiled file
chown root:root TestRunner.class
chmod 444 TestRunner.class
popd

#################################################################
# CLONE OR UPDATE THE HELPER SUBMITTY CODE REPOSITORIES
#################

/bin/bash ${SUBMITTY_REPOSITORY}/.setup/bin/update_repos.sh

if [ $? -eq 1 ]; then
    echo -n "\nERROR: FAILURE TO CLONE OR UPDATE SUBMITTY HELPER REPOSITORIES\n"
    echo -n "Exiting autograder.sh"
    exit 1
fi

#################################################################
# Setup directory structure
#################

echo "Setting up auto-grader directory structure"

mkdir -p ${SUBMITTY_INSTALL_DIR}/test_suite
mkdir -p ${SUBMITTY_INSTALL_DIR}/test_suite/log
cp -r tests/. ${SUBMITTY_INSTALL_DIR}/test_suite

echo "trying to debug " ${PHP_USER} ${CGI_USER}
