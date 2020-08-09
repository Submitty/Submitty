#!/usr/bin/env bash

#####################################################################
#            DEPRECATED
#
# Support for Ubuntu 16.04 (xenial) is deprecated as of 05/01/2019.
# This script has not been maintained since then and we will not
# accept PRs fixing any drift that exists. Use at your own risk.
#
# To see the officially supported distros, please go to:
#     https://submitty.org/sysadmin/server_os
#
#####################################################################


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
    export DATABASE_PORT=15432
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

# Install OpenJDK 8 Non-Interactively
echo "installing java8"
apt-get install -qqy openjdk-8-jdk
update-java-alternatives --set java-1.8.0-openjdk-amd64

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
