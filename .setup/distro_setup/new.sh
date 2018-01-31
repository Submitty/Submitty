#!/usr/bin/env bash

# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit
fi

SUBMITTY_INSTALL_DIR=/usr/local/submitty


##################################################
echo "Getting pylint..."

# following instructions:
# https://codeyarns.com/2015/01/02/how-to-install-pylint-for-python-3/ 

# remove pylint2.0
# apt remove pylint

# install pylint for python3 using pip
apt install -qqy python3-pip
pip3 install pylint

# install gui for pylint
# apt install -qqy python3-tk


##################################################
echo "Getting mono..."
# this package allows us to run windows .net executables on linux

sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys 3FA7E0328081BFF6A14DA29AA6A19B38D3D831EF
echo "deb http://download.mono-project.com/repo/ubuntu xenial main" | sudo tee /etc/apt/sources.list.d/mono-official.list
sudo apt-get -qqy update

sudo apt-get -qqy install mono-devel


##################################################
echo "Getting dafny..."
# "Dafny is a verification-aware programming language"

mkdir -p ${SUBMITTY_INSTALL_DIR}/Dafny
chown root:${COURSE_BUILDERS_GROUP} ${SUBMITTY_INSTALL_DIR}/Dafny
chmod 751 ${SUBMITTY_INSTALL_DIR}/Dafny
pushd ${SUBMITTY_INSTALL_DIR}/Dafny > /dev/null

DAFNY_VER=v2.1.0
DAFNY_FILE=dafny-2.1.0.10108-x64-ubuntu-14.04.zip

wget https://github.com/Microsoft/dafny/releases/download/${DAFNY_VER}/${DAFNY_FILE} -o /dev/null > /dev/null 2>&1
unzip ${DAFNY_FILE} > /dev/null
rm -f ${DAFNY_FILE} > /dev/null

# fix permissions
chmod -R o+rx dafny

popd > /dev/null

# then dafny can be run (using mono):
#    /usr/bin/mono /usr/local/submitty/Dafny/dafny/Dafny.exe /help

##################################################
# unit tests for python
echo "Getting unittest... "

pip3 install unittest2


##  #################################################################
##  
##  pushd /tmp > /dev/null
##  
##  # -----------------------------------------
##  echo "Getting JUnit 4.12 & Hamcrest 1.3..."
##  JUNIT_VER=4.12
##  HAMCREST_VER=1.3
##  mkdir -p ${SUBMITTY_INSTALL_DIR}/JUnit
##  chown root:${COURSE_BUILDERS_GROUP} ${SUBMITTY_INSTALL_DIR}/JUnit
##  chmod 751 ${SUBMITTY_INSTALL_DIR}/JUnit
##  cd ${SUBMITTY_INSTALL_DIR}/JUnit
##  
##  wget http://search.maven.org/remotecontent?filepath=junit/junit/${JUNIT_VER}/junit-${JUNIT_VER}.jar -o /dev/null > /dev/null 2>&1
##  mv remotecontent?filepath=junit%2Fjunit%2F${JUNIT_VER}%2Fjunit-${JUNIT_VER}.jar junit-${JUNIT_VER}.jar
##  wget http://search.maven.org/remotecontent?filepath=org/hamcrest/hamcrest-core/${HAMCREST_VER}/hamcrest-core-${HAMCREST_VER}.jar -o /dev/null > /dev/null 2>&1
##  mv remotecontent?filepath=org%2Fhamcrest%2Fhamcrest-core%2F${HAMCREST_VER}%2Fhamcrest-core-${HAMCREST_VER}.jar hamcrest-core-${HAMCREST_VER}.jar
##  
##  # -----------------------------------------
##  echo "Getting JUnit 5.0 & Hamcrest 2.0..."
##  JUNIT_VER=5.0
##  HAMCREST_VER=2.0
##  mkdir -p ${SUBMITTY_INSTALL_DIR}/JUnit
##  chown root:${COURSE_BUILDERS_GROUP} ${SUBMITTY_INSTALL_DIR}/JUnit
##  chmod 751 ${SUBMITTY_INSTALL_DIR}/JUnit
##  cd ${SUBMITTY_INSTALL_DIR}/JUnit
##  
##  wget http://search.maven.org/remotecontent?filepath=junit/junit/${JUNIT_VER}/junit-${JUNIT_VER}.jar -o /dev/null > /dev/null 2>&1
##  mv remotecontent?filepath=junit%2Fjunit%2F${JUNIT_VER}%2Fjunit-${JUNIT_VER}.jar junit-${JUNIT_VER}.jar
##  wget http://search.maven.org/remotecontent?filepath=org/hamcrest/hamcrest-core/${HAMCREST_VER}/hamcrest-core-${HAMCREST_VER}.jar -o /dev/null > /dev/null 2>&1
##  mv remotecontent?filepath=org%2Fhamcrest%2Fhamcrest-core%2F${HAMCREST_VER}%2Fhamcrest-core-${HAMCREST_VER}.jar hamcrest-core-${HAMCREST_VER}.jar
##  
##  popd > /dev/null
##  
##  
##  # fix permissions
##  pushd ${SUBMITTY_INSTALL_DIR}/JUnit > /dev/null
##  chmod o+r . *.jar
##  popd > /dev/null
##  

#################################################################
