#!/usr/bin/env bash

# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit
fi

if [ ${VAGRANT} == 1 ]; then
    export SUBMISSION_URL='http://192.168.56.111'
    export GIT_URL='http://192.168.56.112'
fi

#################################################################
# PACKAGE SETUP
#################

apt-get install -qqy apt-transport-https ca-certificates curl software-properties-common

# We add this well before we install Java as we can do an apt-get update before our installations
echo "\n" | add-apt-repository ppa:webupd8team/java
echo oracle-java8-installer shared/accepted-oracle-license-v1-1 select true | sudo /usr/bin/debconf-set-selections
apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys EEA14886

apt-get install -qqy python python-dev python3 python3-dev libpython3.6
if [ ! -x "$(command -v pip)" ]; then
    wget --tries=5 https://bootstrap.pypa.io/get-pip.py -O /tmp/get-pip.py
    python3 /tmp/get-pip.py
    rm -f /tmp/get-pip.py
fi

apt-get -qqy update

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
apt-get install -qqy postgresql-10
apt-get install -qqy apache2 apache2-suexec-custom libapache2-mod-authnz-external libapache2-mod-authz-unixgroup libapache2-mod-wsgi-py3
apt-get install -qqy php php-cli php-fpm php-curl php-pgsql php-zip php-mbstring php-xml

if [ ${VAGRANT} == 1 ]; then
    apt-get install -qqy php-xdebug
fi

# Add additional packages for compiling, authentication, and security,
# and program support

# DOCUMENTATION FIXME: Go through this list and categorize purpose of
# these packages (as appropriate.. )

apt-get install -qqy clang autoconf automake autotools-dev diffstat finger gdb git git-man \
p7zip-full patchutils libpq-dev unzip valgrind zip libmagic-ocaml-dev common-lisp-controller \
libboost-all-dev javascript-common  libfile-mmagic-perl libgnupg-interface-perl libbsd-resource-perl \
libarchive-zip-perl gcc g++ g++-multilib jq libseccomp-dev libseccomp2 seccomp junit flex bison spim \
poppler-utils

apt-get install -qqy ninja-build

#CMAKE
echo "installing cmake"
apt-get install -qqy cmake

# Install Oracle 8 Non-Interactively
echo "installing java8"
apt-get install -qqy oracle-java8-installer > /dev/null 2>&1
apt-get install -qqy oracle-java8-set-default

# Install Image Magick for image comparison, etc.
apt-get install -qqy imagemagick

# miscellaneous usability
apt-get install -qqy emacs

curl -fsSL https://download.docker.com/linux/ubuntu/gpg | apt-key add -
apt-key fingerprint 0EBFCD88
add-apt-repository "deb [arch=amd64] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable"
apt-get update
apt-get install -qqy docker-ce
systemctl status docker

apt-get -qqy autoremove
