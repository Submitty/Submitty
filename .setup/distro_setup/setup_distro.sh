#!/usr/bin/env bash

# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit
fi

SOURCE="${BASH_SOURCE[0]}"
CURRENT_DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"
DISTRO=$(lsb_release -si | tr '[:upper:]' '[:lower:]')
VERSION=$(lsb_release -sc | tr '[:upper:]' '[:lower:]')

if [ ! -d ${CURRENT_DIR}/${DISTRO}/${VERSION} ]; then
    (>&2 echo "Unknown distro: ${DISTRO} ${VERSION}")
    exit 1
fi

echo "Setting up distro: ${DISTRO} ${VERSION}"
source ${CURRENT_DIR}/${DISTRO}/${VERSION}/setup_distro.sh

# Install pip after we've installed python within the setup_distro.sh
if [ ! -x "$(command -v pip2)" ] || [ ! -x "$(command -v pip3)" ]; then
    wget --tries=5 https://bootstrap.pypa.io/get-pip.py -O /tmp/get-pip.py
fi

if [ ! -x "$(command -v pip2)" ]; then
    python2 /tmp/get-pip.py
else
    pip2 install -U pip
fi

if [ ! -x "$(command -v pip3)" ]; then
    python3 /tmp/get-pip.py
else
    pip3 install -U pip
fi

if [ -f /tmp/get-pip.py ]; then
    rm -f /tmp/get-pip.py
fi

# Read through our arguments to get "extra" packages to install for our distro
# ${@} are populated by whatever calls install_system.sh which then sources this
# script.
IFS=',' read -ra ADDR <<< "${@}"
if [ ${#ADDR[@]} ]; then
    echo "Installing extra packages..."
    for i in "${ADDR[@]}"; do
        if [ -f ${CURRENT_DIR}/${DISTRO}/${VERSION}/${i}.sh ]; then
            echo "Running ${CURRENT_DIR}/${DISTRO}/${VERSION}/${i}.sh"
            source ${CURRENT_DIR}/${DISTRO}/${VERSION}/${i}.sh
        else
            echo "Could not find ${i}.sh for ${DISTRO} ${VERSION}"
        fi
    done
fi

# check if set, else use default /etc/motd
# we expect that SUBMISSION_URL and GIT_URL to be set above

if [ ${VAGRANT} == 1 ]; then
    # Ubuntu/Debian share this stuff, CentOS does not
    if [ -d /etc/update-motd.d ]; then
        chmod -x /etc/update-motd.d/*
        chmod +x /etc/update-motd.d/00-header
    fi
    if [ -f /usr/share/landscape/landscape-sysinfo.wrapper ]; then
        chmod -x /usr/share/landscape/landscape-sysinfo.wrapper
    fi

    # ${x^^} gives capitalized string
    DISTRO_LINE=$(printf "##  RUNNING: %-44s ##" "${DISTRO^^} ${VERSION^^}")
    # set our cool MOTD here, we expect
    echo -e "
 _______  __   __  _______  __   __  ___   _______  _______  __   __
|       ||  | |  ||  _    ||  |_|  ||   | |       ||       ||  | |  |
|  _____||  | |  || |_|   ||       ||   | |_     _||_     _||  |_|  |
| |_____ |  |_|  ||       ||       ||   |   |   |    |   |  |       |
|_____  ||       ||  _   | |       ||   |   |   |    |   |  |_     _|
 _____| ||       || |_|   || ||_|| ||   |   |   |    |   |    |   |
|_______||_______||_______||_|   |_||___|   |___|    |___|    |___|

############################################################
${DISTRO_LINE}
##                                                        ##
##  All user accounts have same password unless otherwise ##
##  noted below. The following user accounts exist:       ##
##    vagrant/vagrant, root/vagrant, submitty_dbuser,     ##
##    submitty_php, submitty_cgi, submitty_daemon, ta,    ##
##    instructor, developer, postgres                     ##
##                                                        ##
##  The following accounts have database accounts         ##
##  with same password as above:                          ##
##    submitty_dbuser, postgres, root, vagrant            ##
##                                                        ##
##  The VM can be accessed with the following urls:       ##
##    ${SUBMISSION_URL} (submission)                  ##
##    ${SUBMISSION_URL}/cgi-bin (cgi-bin scripts)     ##
##    ${SUBMISSION_URL}/git (git)                     ##
##                                                        ##
##  The database can be accessed on the host machine at   ##
##   localhost:15432                                      ##
##                                                        ##
##  Happy developing!                                     ##
############################################################
" > /etc/motd
    chmod +rx /etc/motd
fi
