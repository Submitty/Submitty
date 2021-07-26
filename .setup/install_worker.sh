#!/usr/bin/env bash

# Usage:
#   install_worker.sh [--no-rpi]

# Read through the flags passed to the script reading them in and setting
# appropriate bash variables, breaking out of this once we hit something we
# don't recognize as a flag

export NO_RPI=0
while :; do
    case $1 in
        --no-rpi)
            export NO_RPI=1
            ;;
        *) # No more options, so break out of the loop.
            break
    esac

    shift
done

# This script is used to set up the worker machine in a vagrant worker pair
# made by running WORKER_PAIR=1 vagrant up

SUPERVISOR_USER=submitty

echo "checking ${SUPERVISOR_USER} user"
# Create the submitty user here on the worker machine
# This is the user that submitty_daemon on the main vagrant machine will ssh into.
if ! cut -d ':' -f 1 /etc/passwd | grep -q ${SUPERVISOR_USER} ; then
    echo "attempting to add ${SUPERVISOR_USER} user"
    #set up submitty user with password 'submitty'
    useradd -m -p $(openssl passwd -crypt submitty) -c "First Last,RoomNumber,WorkPhone,HomePhone" "${SUPERVISOR_USER}"
    [ -d "/home/${SUPERVISOR_USER}" ] && echo "Directory /home/${SUPERVISOR_USER} exists." || echo "Error: Directory /home/${SUPERVISOR_USER} does not exists."
fi

bash ${GIT_PATH}/.setup/install_system.sh --worker 2>&1 | tee ${GIT_PATH}/.vagrant/install_worker_system.log
echo "--- FINISHED INSTALLING SYSTEM ---"
echo "installing worker..."

if [ ${NO_RPI} == 0 ]; then
    sudo bash /usr/local/submitty/GIT_CHECKOUT/Submitty/.setup/distro_setup/ubuntu/rpi.sh
fi

sudo usermod -a -G submitty_daemon ${SUPERVISOR_USER}
sudo usermod -a -G submitty_daemonphp ${SUPERVISOR_USER}
sudo usermod -a -G docker ${SUPERVISOR_USER}
