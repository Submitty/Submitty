#!/usr/bin/env bash

# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit
fi


# We assume a relative path from this repository to the installation
# directory and configuration directory.
CONF_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"/../../../../../config
SUBMITTY_INSTALL_DIR=$(jq -r '.submitty_install_dir' ${CONF_DIR}/submitty.json)

CGI_USER=$(jq -r '.cgi_user' ${SUBMITTY_INSTALL_DIR}/config/submitty_users.json)


##################################################
# This .sh file contains
echo "Installing RPI specific packages"

sudo apt-get install -qqy clisp emacs


##################################################
# Used by Computer Science 1 Autograding
echo "Getting pylint..."

# install pylint for python3 using pip
pip3 install pylint
pip3 install pillow


##################################################
# Used by Principles of Software

echo "Getting mono..."
# this package allows us to run windows .net executables on linux

sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys 3FA7E0328081BFF6A14DA29AA6A19B38D3D831EF
echo "deb http://download.mono-project.com/repo/ubuntu xenial main" | sudo tee /etc/apt/sources.list.d/mono-official.list
sudo apt-get -qqy update

sudo apt-get -qqy install mono-devel

# If Dafny hasn't already been installed
if [ ! -d "${SUBMITTY_INSTALL_DIR}/Dafny" ]; then

    # "Dafny is a verification-aware programming language"
    echo "Getting dafny..."

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
fi


##################################################
# Install Racket and Swi-prolog for Programming Languages
echo "installing Racket and Swi-prolog"
apt-add-repository -y ppa:plt/racket  > /dev/null 2>&1
apt-get install -qqy racket > /dev/null 2>&1
apt-get install -qqy swi-prolog > /dev/null 2>&1



##################################################
# Used by Principles of Program Analysis


# Soot is a Java Bytecode Analysis and Transformation Framework

echo "Getting Soot... "

mkdir -p ${SUBMITTY_INSTALL_DIR}/java_tools/soot

pushd ${SUBMITTY_INSTALL_DIR}/java_tools/soot > /dev/null
rm -rf soot*jar
# older, requested version:
curl http://www.cs.rpi.edu/~milanova/soot-develop.jar > soot-develop.jar
curl http://www.cs.rpi.edu/~milanova/rt.jar > rt.jar
# most recent libraries:
curl https://soot-build.cs.uni-paderborn.de/public/origin/develop/soot/soot-develop/build/sootclasses-trunk.jar > sootclasses-trunk.jar
curl https://soot-build.cs.uni-paderborn.de/public/origin/develop/soot/soot-develop/build/sootclasses-trunk-jar-with-dependencies.jar > sootclasses-trunk-jar-with-dependencies.jar

#
-o /dev/null > /dev/null 2>&1
popd > /dev/null

chown -R root:${COURSE_BUILDERS_GROUP} ${SUBMITTY_INSTALL_DIR}/java_tools
chmod -R 755 ${SUBMITTY_INSTALL_DIR}/java_tools


# install haskell
echo "Getting Haskell... "
apt-get install -qqy haskell-platform
apt-get install -qqy ocaml


## TODO:  ADD INSTALLATION INFO FOR Z3
##        https://github.com/Z3Prover/z3/releases
##    (just installed binary at /usr/local/submitty/tools/z3)


##################################################
# Used by Network Programming class
echo "Getting tools for NetProg... "
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
echo "installing vision libraries"
apt-get install -qqy python3-tk

pip3 install numpy
pip3 install matplotlib
pip3 install opencv-python
pip3 install scipy
pip3 install scikit-image

##################################################
#install some pdflatex packages
apt-get install -qqy texlive-latex-base texlive-extra-utils texlive-latex-recommended
apt-get install -qqy texlive-generic-recommended


# dictionary of words in /usr/share/dict/words
apt-get install -qqy wamerican


# attempt to correct a system with broken dependencies in place
apt-get -f -qqy install


### Fix Python Package Permissions (should always run at the end of this)
# Setting the permissions are necessary as pip uses the umask of the user/system, which
# affects the other permissions (which ideally should be o+rx, but Submitty sets it to o-rwx).
# This gets run here in case we make any python package changes.
find /usr/local/lib/python*/dist-packages -type d -exec chmod 755 {} +
find /usr/local/lib/python*/dist-packages -type f -exec chmod 755 {} +
find /usr/local/lib/python*/dist-packages -type f -name '*.py*' -exec chmod 644 {} +
find /usr/local/lib/python*/dist-packages -type f -name '*.pth' -exec chmod 644 {} +

echo "done with RPI specific installs"
