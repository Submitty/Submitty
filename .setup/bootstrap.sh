#!/usr/bin/env bash

echo -e "
Installing...
 _______  __   __  _______  __   __  ___   _______  _______  __   __
|  _____||  | |  ||  _    ||  |_|  ||   | |       ||       ||  | |  |
| |_____ |  | |  || |_|   ||       ||   | |_     _||_     _||  |_|  |
|_____  ||  |_|  ||  _   | |       ||   |   |   |    |   |  |_     _|
 _____| ||       || |_|   || ||_|| ||   |   |   |    |   |    |   |         
|_______||_______||_______||_|   |_||___|   |___|    |___|    |___|

This script will handle installing and setting up Submitty, including
cloning the repo from https://github.com/Submitty/Submitty and running
.setup/install_system.sh. To accomplish this, the script requires
root access, and will ask for your password if this script was not run
as root.

"

if [ $UID -ne 0 ]; then
    echo "This script requires root to run. Restarting the script under root."
    exec sudo $0 "$@"
    exit $?
fi

set -e

if [ ! $(command -v git ) ]; then
    echo "Installing git..."
    apt-get install -y git
fi

if [ ! -d /usr/local/submitty/GIT_CHECKOUT/Submitty ]; then
    echo "Cloning Submitty..."
    mkdir -p /usr/local/submitty/GIT_CHECKOUT
    git clone https://github.com/Submitty/Submitty /usr/local/submitty/GIT_CHECKOUT/Submitty
fi

if [ ! $(command -v lsb_release) ]; then
    echo "Installing lsb-release..."
    apt-get install -y lsb-release
fi

echo "Running install_system.sh..."
bash /usr/local/submitty/GIT_CHECKOUT/Submitty/.setup/install_system.sh
