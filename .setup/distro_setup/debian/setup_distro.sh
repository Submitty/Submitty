#!/usr/bin/env bash

# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit
fi

SOURCE="${BASH_SOURCE[0]}"
CURRENT_DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"

VERSION=$(lsb_release -r | sed -e "s/Release\:\t\([0-9]*\)\.[0-9]*/\1/")

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
if [ ! -x "$(command -v pip)" ]; then
    wget --tries=5 https://bootstrap.pypa.io/get-pip.py -O /tmp/get-pip.py
    python2 /tmp/get-pip.py
    python3 /tmp/get-pip.py
    rm -f /tmp/get-pip.py
else
    pip2 install -U pip
    pip3 install -U pip
fi

if [ ${VAGRANT} == 1 ]; then
    #
    # The goal here is to ensure the VM is accessible from your own
    # computer for code testing, has an outgoing connection to the
    # Internet to access github and receive Debian updates, but is also
    # unreachable via incoming Internet connections so to block uninvited
    # guests.
    #
    # The VM’s host-only adapter provides a private connection to the VM,
    # but Ubuntu also needs to be configured to use this adapter.

    echo "Binding static IPs to \"Host-Only\" virtual network interface."

    # eth0 is auto-configured by Vagrant as NAT.  eth1 is a host-only adapter and
    # not auto-configured.  eth1 is manually set so that the host-only network
    # interface remains consistent among VM reboots as Vagrant has a bad habit of
    # discarding and recreating networking interfaces everytime the VM is restarted.
    # eth1 is statically bound to 192.168.56.101.
    printf "auto eth1\niface eth1 inet static\naddress 192.168.56.102\nnetmask 255.255.255.0\n\n" >> /etc/network/interfaces.d/eth1.cfg

    # Turn them on.
    ifup eth1

    export SUBMISSION_URL='http://192.168.56.102'
    rm /etc/motd
    echo -e '
 _______  __   __  _______  __   __  ___   _______  _______  __   __
|       ||  | |  ||  _    ||  |_|  ||   | |       ||       ||  | |  |
|  _____||  | |  || |_|   ||       ||   | |_     _||_     _||  |_|  |
| |_____ |  |_|  ||       ||       ||   |   |   |    |   |  |       |
|_____  ||       ||  _   | |       ||   |   |   |    |   |  |_     _|
 _____| ||       || |_|   || ||_|| ||   |   |   |    |   |    |   |
|_______||_______||_______||_|   |_||___|   |___|    |___|    |___|

############################################################
##  All user accounts have same password unless otherwise ##
##  noted below. The following user accounts exist:       ##
##    vagrant/vagrant, root/vagrant, hsdbu, hwphp,        ##
##    hwcgi hwcron, ta, instructor, developer,            ##
##    postgres                                            ##
##                                                        ##
##  The following accounts have database accounts         ##
##  with same password as above:                          ##
##    hsdbu, postgres, root, vagrant                      ##
##                                                        ##
##  The VM can be accessed with the following urls:       ##
##    http://192.168.56.102 (submission)                  ##
##    http://192.168.56.102/cgi-bin (cgi-bin scripts)     ##
##    http://192.168.56.102/hwgrading (tagrading)         ##
##                                                        ##
##  The database can be accessed on the host machine at   ##
##   localhost:25432                                      ##
##                                                        ##
##  Happy developing!                                     ##
############################################################
' > /etc/motd
    chmod +rx /etc/motd
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
apt-get install -qy php7.0 php7.0-cli php7.0-xdebug libapache2-mod-fastcgi php7.0-fpm php7.0-curl php7.0-pgsql php7.0-mcrypt
apt-get install -qqy php7.0-zip

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
