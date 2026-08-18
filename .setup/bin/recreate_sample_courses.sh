#!/bin/bash

# Automated regeneration of sample course data

########################################################################
FLAG=
COURSES=

for arg in "$@"; do
    case "$arg" in
        --no_submissions)
            FLAG="--no_submissions"
            ;;
        --test_only_grading)
            FLAG="--test_only_grading"
            ;;
        *)
            # interpret everything else as a course name
            COURSES+="$arg "
            ;;
    esac
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

source /usr/local/submitty/venv/bin/activate

python3 ./.setup/bin/partial_reset.py
python3 ./.setup/bin/setup_sample_courses.py ${FLAG} ${COURSES}

# Resets the submitty-admin API token (cleared by partial_reset.py) before setup_sample_courses.py restarts daemons.
# Without this, submitty_daemon_jobs_handler fails to start when reading submitty_users.json for the missing token.
python3 /usr/local/submitty/.setup/bin/init_auto_rainbow.py

DAEMONS=( submitty_websocket_server submitty_autograding_shipper submitty_autograding_worker submitty_daemon_jobs_handler )
for i in "${DAEMONS[@]}"; do
    systemctl stop ${i}
    systemctl reset-failed ${i} # submitty_daemon_jobs_handler will fail to start in setup_sample_courses.py
done

PHP_VERSION=$(php -r 'print PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
service php${PHP_VERSION}-fpm restart

DAEMONS=( submitty_websocket_server submitty_autograding_shipper submitty_autograding_worker submitty_daemon_jobs_handler )
for i in "${DAEMONS[@]}"; do
    systemctl start ${i}
done
