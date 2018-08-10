#!/usr/bin/env bash

# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit
fi

apt-get install -qqy software-properties-common
echo "\n" | add-apt-repository ppa:webupd8team/java
echo oracle-java8-installer shared/accepted-oracle-license-v1-1 select true | sudo /usr/bin/debconf-set-selections
apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys EEA14886

apt-get update -qqy
apt-get install -qqy python python-dev python3 python3-dev libpython3.5

if [ ${VAGRANT} == 1 ]; then
    export SUBMISSION_URL='http://192.168.56.101'
    export GIT_URL='http://192.168.56.101'
fi

#################################################################
# PACKAGE SETUP
#################

apt-get update -qqy

############################
# NTP: Network Time Protocol
# You want to be sure the clock stays in sync, especially if you have
# deadlines for homework to be submitted.
#
# The default settings are ok, but you can edit /etc/ntp.conf and
# replace the default servers with your local ntp server to reduce
# traffic through your campus uplink (To replace the default servers
# with your own, comment out the default servers by adding a # before
# the lines that begin with “server” and add your server under the
# line that says “Specify one or more NTP servers” with something
# along the lines of “server xxx.xxx.xxx.xxx”)

apt-get install -qqy ntp
service ntp restart

echo "Preparing to install packages.  This may take a while."

apt-get install -qqy libpam-passwdqc

# Set up apache to run with suphp in pre-fork mode since not all
# modules are thread safe (do not combine the commands or you may get
# the worker/threaded mode instead)

apt-get install -qqy ssh sshpass unzip
apt-get install -qqy postgresql-9.5
apt-get install -qqy apache2 apache2-suexec-custom libapache2-mod-authnz-external libapache2-mod-authz-unixgroup libapache2-mod-wsgi-py3
apt-get install -qqy php7.0 php7.0-cli php7.0-fpm php7.0-curl php7.0-pgsql php7.0-mcrypt php7.0-zip php7.0-mbstring php7.0-xml

if [ ${VAGRANT} == 1 ]; then
    apt-get install -qqy php7.0-sqlite3 php-xdebug
fi

#Add the scrot screenshotting program
apt-get install -qqy scrot

# Add additional packages for compiling, authentication, and security,
# and program support

# DOCUMENTATION FIXME: Go through this list and categorize purpose of
# these packages (as appropriate.. )

apt-get install -qqy clang autoconf automake autotools-dev diffstat finger gdb git git-man \
hardening-includes p7zip-full patchutils \
libpq-dev unzip valgrind zip libmagic-ocaml-dev common-lisp-controller libboost-all-dev \
javascript-common  \
libfile-mmagic-perl libgnupg-interface-perl libbsd-resource-perl libarchive-zip-perl gcc g++ \
g++-multilib jq libseccomp-dev libseccomp2 seccomp junit flex bison spim poppler-utils

apt-get install -qqy ninja-build

#CMAKE
echo "installing cmake"
apt-get install -qqy cmake

# for Lichen (Plagiarism Detection)
apt-get -qqy install python-clang-3.8

# Install Oracle 8 Non-Interactively
echo "installing java8"

# Try to install Java.... But sometimes they release new security updates faster
# than the package managers

# Run this in a subshell so it doesn't crash the script when Java fails to install
GOT_JAVA=$(bash -c 'apt-get install -qqy oracle-java8-installer > /dev/null 2>&1; echo $?')
# If it didn't work, our package manager is out of date so we need to 
# patch in the updated version 
if [ $GOT_JAVA -ne 0 ]; then
    pushd .
    # https://askubuntu.com/a/996986
    cd /var/lib/dpkg/info
    sed -i 's|JAVA_VERSION=8u171|JAVA_VERSION=8u181|' oracle-java8-installer.*
    sed -i 's|J_DIR=jdk1.8.0_171|J_DIR=jdk1.8.0_181|' oracle-java8-installer.*
    sed -i 's|PARTNER_URL=http://download.oracle.com/otn-pub/java/jdk/8u171-b11/512cd62ec5174c3487ac17c61aaa89e8/|PARTNER_URL=http://download.oracle.com/otn-pub/java/jdk/8u181-b13/96a7b8442fe848ef90c96a2fad6ed6d1/|' oracle-java8-installer.*
    sed -i 's|SHA256SUM_TGZ="b6dd2837efaaec4109b36cfbb94a774db100029f98b0d78be68c27bec0275982"|SHA256SUM_TGZ="1845567095bfbfebd42ed0d09397939796d05456290fb20a83c476ba09f991d3"|' oracle-java8-installer.*
    popd
    # Try again! If this fails then someone needs to update the above for whatever
    # new version of Java has come out. 
    apt-get install -qqy oracle-java8-installer > /dev/null 2>&1
fi

apt-get install -qqy oracle-java8-set-default

# Install Image Magick for image comparison, etc.
apt-get install -qqy imagemagick

curl -fsSL https://download.docker.com/linux/ubuntu/gpg | apt-key add -
add-apt-repository "deb [arch=amd64] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable"
apt-get update -qqy
apt-get install -qqy -y docker-ce
systemctl status docker | head -n 100


if [ ${VAGRANT} == 1 ]; then
    apt-get install -qqy gitweb libcgi-session-perl
fi

apt-get -qqy autoremove

# miscellaneous usability
apt-get install -qqy emacs
