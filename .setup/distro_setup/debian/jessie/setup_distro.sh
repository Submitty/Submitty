#!/usr/bin/env bash

# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit
fi

# Add repo for Java 8
apt-get install -qy software-properties-common
add-apt-repository "deb http://ppa.launchpad.net/webupd8team/java/ubuntu xenial main"
echo oracle-java8-installer shared/accepted-oracle-license-v1-1 select true | sudo /usr/bin/debconf-set-selections
apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys EEA14886

# Some non-free repos
add-apt-repository "deb http://ftp.debian.org/debian/ jessie main contrib non-free"

# Add repo to make it possible to use PHP 7 instead of the PHP that comes with Jessie (5.6)
add-apt-repository 'deb http://packages.dotdeb.org jessie all'
wget https://www.dotdeb.org/dotdeb.gpg -O /tmp/dotdeb.gpg
apt-key add /tmp/dotdeb.gpg
rm /tmp/dotdeb.gpg

add-apt-repository "deb http://ftp.debian.org/debian jessie-backports main"

apt-get update

apt-get install -qy python python-dev python3 python3-dev libpython3.4

if [ ${VAGRANT} == 1 ]; then
    export SUBMISSION_URL='http://192.168.56.201'
    export GIT_URL='http://192.168.56.202'
fi

############################
# NTP: Network Time Protocol
# You want to be sure the clock stays in sync, especially if you have
# deadlines for homework to be submitted.
apt-get install -qqy ntp
service ntp restart

apt-get install -qqy libpam-passwdqc
apt-get install -qqy ssh sshpass unzip
apt-get install -qqy postgresql-9.4 postgresql-contrib-9.4
apt-get install -qqy apache2 apache2-suexec-custom libapache2-mod-authnz-external libapache2-mod-authz-unixgroup
apt-get install -qy php7.0 php7.0-cli libapache2-mod-fastcgi php7.0-fpm php7.0-curl php7.0-pgsql php7.0-mcrypt
apt-get install -qqy php7.0-zip php7.0-mbstring php7.0-xml

if [ ${VAGRANT} == 1 ]; then
    apt-get install -qqy php7.0-sqlite3 php7.0-xdebug
fi

# TODO: removed packages:
#   clisp (we should probably stop using it for Ubuntu?)
apt-get install -qqy clang autoconf automake autotools-dev diffstat emacs finger gdb git git-man \
hardening-includes p7zip-full patchutils \
libpq-dev unzip valgrind zip libmagic-ocaml-dev common-lisp-controller libboost-all-dev \
javascript-common  \
libfile-mmagic-perl libgnupg-interface-perl libbsd-resource-perl libarchive-zip-perl gcc g++ \
g++-multilib jq flex bison spim poppler-utils pdftk

apt-get -t jessie-backports install libseccomp-dev libseccomp2 seccomp

#CMAKE
echo "installing cmake"
apt-get install -qqy cmake

#GLEW and GLM
echo "installing graphics libraries"
apt-get install -qqy glew-utils libglew-dev libglm-dev
apt-get install -qqy libxrandr-dev xorg-dev

#CMAKE permissions
#These permissions are necessary so that untrusted user can use pkgconfig with cmake.
#Note that pkgconfig does not appear until after graphics installs (Section above)
chmod -R o+rx /usr/local/lib/pkgconfig
chmod -R o+rx /usr/local/lib/cmake

# TODO: Skipping Racket, Prolog, GLFW as these aren't tested currently and are "extra" packages anyway


# Install Oracle 8 Non-Interactively
echo "installing java8"
apt-get install -qqy oracle-java8-installer > /dev/null 2>&1
apt-get install -qqy oracle-java8-set-default

# Install Image Magick for image comparison, etc.
apt-get install -qqy imagemagick

apt-get -qqy autoremove
