#!/bin/bash

SUBMITTY_INSTALL_DIR=/usr/local/submitty
SUBMITTY_REPOSITORY=/usr/local/submitty/GIT_CHECKOUT/Submitty
SUBMITTY_DATA_DIR=/var/local/submitty

mkdir -p ${SUBMITTY_INSTALL_DIR}
mkdir -p ${SUBMITTY_DATA_DIR}
mkdir -p ${SUBMITTY_INSTALL_DIR}/src
cp -r grading/ ${SUBMITTY_INSTALL_DIR}/src/

# --------------------------------------
echo "Getting DrMemory..."
mkdir -p ${SUBMITTY_INSTALL_DIR}/DrMemory
pushd /tmp
DRMEM_TAG=release_1.10.1
DRMEM_VER=1.10.1-3
wget https://github.com/DynamoRIO/drmemory/releases/download/${DRMEM_TAG}/DrMemory-Linux-${DRMEM_VER}.tar.gz -o /dev/null > /dev/null 2>&1
tar -xpzf DrMemory-Linux-${DRMEM_VER}.tar.gz -C ${SUBMITTY_INSTALL_DIR}/DrMemory
ln -s ${SUBMITTY_INSTALL_DIR}/DrMemory/DrMemory-Linux-${DRMEM_VER} ${SUBMITTY_INSTALL_DIR}/drmemory
rm DrMemory-Linux-${DRMEM_VER}.tar.gz
popd

# --------------------------------------
echo "Getting JUnit..."
mkdir -p ${SUBMITTY_INSTALL_DIR}/JUnit
chmod 751 ${SUBMITTY_INSTALL_DIR}/JUnit
pushd ${SUBMITTY_INSTALL_DIR}/JUnit

JUNIT_VER=4.12
HAMCREST_VER=1.3

wget http://repo1.maven.org/maven2/junit/junit/${JUNIT_VER}/junit-${JUNIT_VER}.jar -o /dev/null > /dev/null 2>&1
wget http://repo1.maven.org/maven2/org/hamcrest/hamcrest-core/${HAMCREST_VER}/hamcrest-core-${HAMCREST_VER}.jar -o /dev/null > /dev/null 2>&1

# EMMA is a tool for computing code coverage of Java programs
echo "Getting emma..."
EMMA_VER=2.0.5312
wget https://github.com/Submitty/emma/archive/${EMMA_VER}.zip -O emma-${EMMA_VER}.zip -o /dev/null > /dev/null 2>&1
unzip emma-${EMMA_VER}.zip > /dev/null
mv emma-${EMMA_VER}/lib/emma.jar emma.jar
rm -rf emma-${EMMA_VER}
rm emma-${EMMA_VER}.zip
rm index.html* > /dev/null 2>&1
chmod o+r . *.jar

# JaCoCo is a potential replacement for EMMA
echo "Getting JaCoCo..."
JACOCO_VER=0.8.0
wget https://github.com/jacoco/jacoco/releases/download/v${JACOCO_VER}/jacoco-${JACOCO_VER}.zip -o /dev/null > /dev/null 2>&1
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
cp junit_test_runner/TestRunner.java $SUBMITTY_INSTALL_DIR/JUnit/TestRunner.java

pushd $SUBMITTY_INSTALL_DIR/JUnit
# root will be owner & group of the source file
chown  root:root  TestRunner.java
# everyone can read this file
chmod  444 TestRunner.java

# compile the executable
javac -cp ./junit-4.12.jar TestRunner.java

# everyone can read the compiled file
chown root:root TestRunner.class
chmod 444 TestRunner.class
popd


#################################################################
# CLONE OR UPDATE THE HELPER SUBMITTY CODE REPOSITORIES
#################

echo "in autograder.sh"

pwd

bash ${SUBMITTY_REPOSITORY}/.setup/bin/update_repos.sh

if [ $? -eq 1 ]; then
    echo -n "\nERROR: FAILURE TO CLONE OR UPDATE SUBMITTY HELPER REPOSITORIES\n"
    echo -n "Exiting autograder.sh"
    exit 1
fi
