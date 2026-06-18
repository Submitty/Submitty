#!/usr/bin/env bash

# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit
fi

if [ ${DEV_VM} == 1 ]; then
    export SUBMISSION_URL='http://localhost:1511'
    export SUBMISSION_PORT=1511
    export DATABASE_PORT=16442
    export WEBSOCKET_PORT=8443
fi

#################################################################
# PACKAGE SETUP
#################

# Need to change this otherwise it will hang the script in interactive mode
sed -i "s/#\$nrconf{restart} = 'i';/\$nrconf{restart} = 'a';/" /etc/needrestart/needrestart.conf

add-apt-repository -y ppa:ondrej/php
apt-get -qqy update

apt-get install -qqy apt-transport-https ca-certificates curl software-properties-common
apt-get install -qqy python3 python3-dev libpython3.6 python3-pip python3-venv

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
apt-get install -qqy postgresql-14
apt-get install -qqy apache2 apache2-suexec-custom libapache2-mod-authnz-external libapache2-mod-authz-unixgroup libapache2-mod-wsgi-py3
apt-get install -qqy php8.2-cli php8.2-fpm php8.2-curl php8.2-pgsql php8.2-zip php8.2-mbstring php8.2-xml php8.2-ds php8.2-imagick php8.2-intl

if [ ${DEV_VM} == 1 ]; then
    apt-get install -qqy php8.2-xdebug php8.2-ldap php8.2-sqlite3
fi

#Add the scrot screenshotting program
apt-get install -qqy scrot

# Add additional packages for compiling, authentication, and security,
# and program support

# DOCUMENTATION FIXME: Go through this list and categorize purpose of
# these packages (as appropriate.. )

apt-get install -qqy clang autoconf automake autotools-dev diffstat finger gdb \
p7zip-full patchutils libpq-dev unzip valgrind zip libboost-all-dev gcc g++ \
jq libseccomp-dev libseccomp2 seccomp junit flex bison poppler-utils

apt-get install -qqy ninja-build

# NodeJS
NODE_MAJOR=20
sudo rm -f /etc/apt/keyrings/nodesource.gpg
curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | sudo gpg --batch --dearmor -o /etc/apt/keyrings/nodesource.gpg
chmod o+r /etc/apt/keyrings/nodesource.gpg
echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_MAJOR.x nodistro main" | sudo tee /etc/apt/sources.list.d/nodesource.list
apt-get update
apt-get install -y nodejs

#CMAKE
echo "installing cmake"
apt-get install -qqy cmake

# for Lichen (Plagiarism Detection)
#apt-get install -qqy python-clang-6.0

# Install Image Magick for image comparison, etc.
apt-get install -qqy imagemagick

# miscellaneous usability
apt-get install -qqy emacs

curl -fsSL https://download.docker.com/linux/ubuntu/gpg | apt-key add -
apt-key fingerprint 0EBFCD88

add-apt-repository "deb [arch=amd64,arm64] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" -y
apt-get update
apt-get install -qqy docker-ce docker-ce-cli
systemctl status docker | head -n 100

apt-get -qqy autoremove

# ------------------------------------------------------------------
# upgrade git to 2.28.0 or greater
# https://git-scm.com/docs/git-init/2.28.0
# need the --initial-branch option
# for creating student submission vcs git repositories
add-apt-repository ppa:git-core/ppa -y
apt-get install git -y
# ------------------------------------------------------------------

# Install OpenLDAP for testing on Vagrant
if [ ${DEV_VM} == 1 ]; then
    CUR_DIR="$( cd -P "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

    source "$CUR_DIR/../../../vagrant/setup_ldap.sh"
fi

# Install SAML IdP docker container for testing
if [ ${DEV_VM} == 1 ]; then
    docker pull submitty/docker-test-saml-idp:latest
fi
