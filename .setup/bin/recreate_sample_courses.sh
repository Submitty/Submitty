#!/bin/bash

# Automated regeneration of sample course data

########################################################################
EXTRA=

while :; do
    case $1 in
        --no_submissions)
            EXTRA="--no_submissions"
            ;;
        *) # No more options, so break out of the loop.
            break
    esac

    shift
done

# If any command fails, we need to bail
set -ev

# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit 1
fi

# Get into the script's directory
DIR=`echo $0 | sed -E 's/\/[^\/]+$/\//'`
if [ "X$0" != "X$DIR" ]; then
   cd "$DIR"
fi

# GIT_CHECKOUT/Submitty/.setup/bin -> GIT_CHECKOUT/Submitty
cd ../../

python3 ./.setup/bin/partial_reset.py
python3 ./.setup/bin/setup_sample_courses.py ${EXTRA}

PHP_VERSION=$(php -r 'print PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
service php${PHP_VERSION}-fpm restart

DAEMONS=( submitty_websocket_server submitty_autograding_shipper submitty_autograding_worker submitty_daemon_jobs_handler )
for i in "${DAEMONS[@]}"; do
    systemctl start ${i}
done
