#!/usr/bin/env bash

# Usage:
#   install_worker.sh


GIT_PATH=/usr/local/submitty/GIT_CHECKOUT/Submitty
SUPERVISOR_USER=submitty

adduser ${SUPERVISOR_USER}

bash ${GIT_PATH}/.setup/install_system.sh --worker 2>&1 | tee ${GIT_PATH}/.vagrant/install_worker_system.log
echo "--- FINISHED INSTALLING SYSTEM ---"
echo "installing worker..."

sudo bash /usr/local/submitty/GIT_CHECKOUT/Submitty/.setup/distro_setup/ubuntu/rpi.sh

sudo usermod -a -G submitty_daemon ${SUPERVISOR_USER}
sudo usermod -a -G submitty_daemonphp ${SUPERVISOR_USER}
sudo usermod -a -G docker ${SUPERVISOR_USER}