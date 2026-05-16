#!/usr/bin/env bash

# Usage:
#   install_worker.sh [<extra> <extra> ...]

# This script is used to set up the worker machine in a vagrant worker pair
# made by running WORKERS=n vagrant up

GIT_PATH=/usr/local/submitty/GIT_CHECKOUT/Submitty
SUPERVISOR_USER=submitty

output=$(cut -d ':' -f 1 /etc/passwd | grep -w "^${SUPERVISOR_USER}$")
# Create the submitty user here on the worker machine
# This is the user that submitty_daemon on the main vagrant machine will ssh into.
if [[ "${output}" != "submitty" ]]; then
    echo "attempting to add ${SUPERVISOR_USER} user"
    #set up submitty user with password 'submitty'
    useradd -m -p "$(openssl passwd -1 submitty)" -c "First Last,RoomNumber,WorkPhone,HomePhone" "${SUPERVISOR_USER}"
    [ -d "/home/${SUPERVISOR_USER}" ] && echo "Directory /home/${SUPERVISOR_USER} exists." || echo "Error: Directory /home/${SUPERVISOR_USER} does not exists."
    else
        echo "Error: ${SUPERVISOR_USER} user already exists in /etc/passwd"
fi

bash "${GIT_PATH}/.setup/install_system.sh" --worker --vagrant "${@}" 2>&1
echo "--- FINISHED INSTALLING SYSTEM ---"
echo "installing worker..."

sudo usermod -a -G submitty_daemon    "${SUPERVISOR_USER}"
sudo usermod -a -G submitty_daemonphp "${SUPERVISOR_USER}"
sudo usermod -a -G docker             "${SUPERVISOR_USER}"
