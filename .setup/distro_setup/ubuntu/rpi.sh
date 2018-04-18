#!/usr/bin/env bash

# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit
fi

SUBMITTY_INSTALL_DIR=/usr/local/submitty


##################################################
# This .sh file contains
echo "Installing RPI specific packages"

sudo apt-get install -qqy clisp emacs


##################################################
# Used by Computer Science 1 Autograding
echo "Getting pylint..."

# install pylint for python3 using pip
apt install -qqy python3-pip
pip3 install pylint
pip3 install pillow

# unit tests for python
echo "Getting unittest... "

pip3 install unittest2


##################################################
# Used by Principles of Software

echo "Getting mono..."
# this package allows us to run windows .net executables on linux

sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys 3FA7E0328081BFF6A14DA29AA6A19B38D3D831EF
echo "deb http://download.mono-project.com/repo/ubuntu xenial main" | sudo tee /etc/apt/sources.list.d/mono-official.list
sudo apt-get -qqy update

sudo apt-get -qqy install mono-devel

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
# Install Racket and Swi-prolog for Programming Languages
echo "installing Racket and Swi-prolog"
apt-add-repository -y ppa:plt/racket  > /dev/null 2>&1
apt-get install -qqy racket > /dev/null 2>&1
apt-get install -qqy swi-prolog > /dev/null 2>&1



##################################################
# Used by Principles of Program Analysis

# TODO: add download & install for soot-develop.jar & rt.jar
# target:  /usr/local/submity/tools/soot/

# install haskell
apt-get install -qqy haskell-platform

##################################################
# Used by Network Programming class
apt-get install -qqy libssl-dev

# don't install these...
#apt-get install -qqy libavahi-compat-libdnssd-dev avahi-utils avahi-daemon

# instead:
mkdir tmp_avahi_install_dir
cd tmp_avahi_install_dir
apt-get download libavahi-compat-libdnssd-dev
mv libavahi*deb libavahi-compat-libdnssd-dev.deb
dpkg --force-all -i libavahi-compat-libdnssd-dev.deb
cd ..
rm -r tmp_avahi_install_dir

##################################################
# Used by Advanced Computer Graphics course
# GLEW and GLM
echo "installing graphics libraries"
apt-get install -qqy glew-utils libglew-dev libglm-dev
apt-get install -qqy libxrandr-dev xorg-dev

#CMAKE permissions
#These permissions are necessary so that untrusted user can use pkgconfig with cmake.
#Note that pkgconfig does not appear until after graphics installs (Section above)
chmod -R o+rx /usr/local/lib/pkgconfig
chmod -R o+rx /usr/local/lib/cmake

#GLFW
echo "installing GLFW"
wget https://github.com/glfw/glfw/releases/download/3.2.1/glfw-3.2.1.zip
unzip glfw-3.2.1.zip
cd glfw-3.2.1
mkdir build
cd build
cmake ..
make
sudo make install
cd ../..
rm -R glfw-3.2.1
rm glfw-3.2.1.zip


##################################################
# Used by Computational Vision course
apt-get install python3-tk

pip3 install -U pip numpy
pip3 install matplotlib
pip3 install opencv-python


##################################################
# Fixup the permissions
chmod -R 555 /usr/local/lib/python*/*
chmod 555 /usr/lib/python*/dist-packages
sudo chmod 500   /usr/local/lib/python*/dist-packages/pam.py*
sudo chown hwcgi /usr/local/lib/python*/dist-packages/pam.py*


##################################################
#install some pdflatex packages
apt-get install -qqy  texlive-latex-base texlive-extra-utils texlive-latex-recommended
