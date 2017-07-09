#!/bin/bash

SUBMITTY_INSTALL_DIR=/usr/local/submitty
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
pushd ${SUBMITTY_INSTALL_DIR}/JUnit

wget http://search.maven.org/remotecontent?filepath=junit/junit/4.12/junit-4.12.jar -o /dev/null > /dev/null 2>&1
mv remotecontent?filepath=junit%2Fjunit%2F4.12%2Fjunit-4.12.jar junit-4.12.jar
wget http://search.maven.org/remotecontent?filepath=org/hamcrest/hamcrest-core/1.3/hamcrest-core-1.3.jar -o /dev/null > /dev/null 2>&1
mv remotecontent?filepath=org%2Fhamcrest%2Fhamcrest-core%2F1.3%2Fhamcrest-core-1.3.jar hamcrest-core-1.3.jar

# EMMA is a tool for computing code coverage of Java programs
echo "Getting emma..."
wget https://github.com/Submitty/emma/releases/download/2.0.5312/emma-2.0.5312.zip -o /dev/null > /dev/null 2>&1
unzip emma-2.0.5312.zip > /dev/null
mv emma-2.0.5312/lib/emma.jar emma.jar
rm -rf emma-2.0.5312
rm emma-2.0.5312.zip
rm index.html* > /dev/null 2>&1

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

# --------------------------------------
echo -e "Compile and install analysis tools"
git clone 'https://github.com/Submitty/AnalysisTools' ${SUBMITTY_INSTALL_DIR}/GIT_CHECKOUT_AnalysisTools

pushd /usr/local/submitty/GIT_CHECKOUT_AnalysisTools


# git checkout -b  VERSION_2_2  v0.2.2
stack --allow-different-user --install-ghc --copy-bins build


popd
mkdir /usr/local/submitty/SubmittyAnalysisTools
cp /usr/local/submitty/GIT_CHECKOUT_AnalysisTools/count /usr/local/submitty/SubmittyAnalysisTools

# --------------------------------------
echo -e "Compile and install the tutorial repository"
git clone 'https://github.com/Submitty/Tutorial' ${SUBMITTY_INSTALL_DIR}/GIT_CHECKOUT_Tutorial
