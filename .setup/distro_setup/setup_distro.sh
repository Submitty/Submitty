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
        chmod -x /usr/share/landscape/landscape-sysinfo.wrapper
        chmod +x /etc/update-motd.d/00-header
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
##    vagrant/vagrant, root/vagrant, hsdbu, hwphp,        ##
##    hwcgi hwcron, ta, instructor, developer,            ##
##    postgres                                            ##
##                                                        ##
##  The following accounts have database accounts         ##
##  with same password as above:                          ##
##    hsdbu, postgres, root, vagrant                      ##
##                                                        ##
##  The VM can be accessed with the following urls:       ##
##    ${SUBMISSION_URL} (submission)                  ##
##    ${SUBMISSION_URL}/cgi-bin (cgi-bin scripts)     ##
##    ${GIT_URL}/git (git)                     ##
##                                                        ##
##  The database can be accessed on the host machine at   ##
##   localhost:15432                                      ##
##                                                        ##
##  Happy developing!                                     ##
############################################################
" > /etc/motd
    chmod +rx /etc/motd
fi


# We don't want to install pip via the system as we get an old version
# and upgrading it is not recommended
if [ ! -x "$(command -v pip)" ]; then
    wget --tries=5 https://bootstrap.pypa.io/get-pip.py -O /tmp/get-pip.py
    python2 /tmp/get-pip.py
    python3 /tmp/get-pip.py
    rm -f /tmp/get-pip.py
else
    pip2 install -U pip
    pip3 install -U pip
fi
