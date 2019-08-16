#!/usr/bin/env bash

function check_exit_code {
# $1 = process return code
# $2 = name of process
    if [[ $1 -ne 0 ]]; then
        echo -e "Error setting up $2.  Aborting."
        exit 1
    else
        echo -e "Successfully set up $2."
    fi
}

# Root required
if [[ $EUID -ne 0 ]]; then
   echo -e "This script must be run as root"
   exit 1
fi

# CLI argument "auto" will assume user permission to setup preferred name logging.
if [[ $1 != "auto" ]]; then
    echo -e "\e[1mTo enable preferred name logging, changes are required.

The following changes will be made in /etc/postgresql/10/main/postgresql.conf:\e[0m
log_destination = 'cvslog'
logging_collector = on
log_directory = '${SUBMITTY_DATA_DIR}/logs/psql'
log_filename = 'postgresql-%Y-%m-%d_%H%M%S.log'
log_file_mode = 0640
log_rotation_age = 1d
log_rotation_size = 10MB
log_min_messages = log
log_min_duration_statement = 0
log_line_prefix = '%m [%p] %q%u@%d '

\e[1mThe following entry will be added to cron:\e[0m
# Run preferred_name_logging.php every night at 2:05AM
5 2 * * * submitty_daemon   python3 ${SUBMITTY_INSTALL_DIR}/sbin/preferred_name_logging.php -s submitty

\e[1mMake these changes and setup preferred name tracking? [y]es/[N]o?\e[0m"

    read user_permission
else
    # Automatic setup.  Assumes that user gave permission.
    echo -e "Automatically setting up preferred name logging."
    user_permission="y"
fi

if [[ ${user_permission:0:1} == "y" ]] || [[ ${user_permission:0:1} == "Y" ]]; then
    # Copy preferred_name_logging.php to sbin
    rsync -qt ${SUBMITTY_INSTALL_DIR}/GIT_CHECKOUT/SysadminTools/preferred_name_logging/preferred_name_logging.php ${SUBMITTY_INSTALL_DIR}/sbin
    check_exit_code $? "preferred_name_user.php"
    chown root:${DAEMON_GROUP} ${SUBMITTY_INSTALL_DIR}/sbin/preferred_name_logging.php
    chmod 0640 ${SUBMITTY_INSTALL_DIR}/sbin/preferred_name_logging.php

    # Adjust/overwrite Postgresql's configuration
    sed -i "s~^#*[ tab]*log_destination[ tab]*=[ tab]*['a-zA-Z0-9_]*~log_destination = 'csvlog'~;
            s~^#*[ tab]*logging_collector[ tab]*=[ tab]*['a-zA-Z0-9_]*~logging_collector = on~;
            s~^#*[ tab]*log_directory[ tab]*=[ tab]*['a-zA-Z0-9_]*~log_directory = '${SUBMITTY_DATA_DIR}/logs/psql'~;
            s~^#*[ tab]*log_filename[ tab]*=[ tab]*['a-zA-Z0-9_]*~log_filename = 'postgresql-%Y-%m-%d_%H%M%S.log'~;
            s~^#*[ tab]*log_file_mode[ tab]*=[ tab]*['a-zA-Z0-9_]*~log_file_mode = 0640~;
            s~^#*[ tab]*log_rotation_age[ tab]*=[ tab]*['a-zA-Z0-9_]*~log_rotation_age = 1d~;
            s~^#*[ tab]*log_rotation_size[ tab]*=[ tab]*['a-zA-Z0-9_]*~log_rotation_size = 10MB~;
            s~^#*[ tab]*log_min_messages[ tab]*=[ tab]*['a-zA-Z0-9_]*~log_min_messages = log~;
            s~^#*[ tab]*log_min_duration_statement[ tab]*=[ tab]*['a-zA-Z0-9_]*~log_min_duration_statement = 0~;
            s~^#*[ tab]*log_line_prefix[ tab]*=[ tab]*['a-zA-Z0-9_]*~log_line_prefix = '%m [%p] %q%u@%d '~" /etc/postgresql/10/main/postgresql.conf
    check_exit_code $? "Postgresql.conf"

    # Check to see if crontab entry already exists
    grep -qs "${SUBMITTY_INSTALL_DIR}/sbin/preferred_name_logging.php" /etc/cron.d/submitty
    if [[ $? -eq 0 ]]; then
        # Entry exists, re-adjust it to run at 2:05AM by submitty_daemon
        sed -ie "s~^#*[ tab]*[0-9\*][0-9]*[ tab][0-9\*][0-9]*[ tab][0-9\*][0-9]*[ tab][0-9\*][0-9]*[ tab][0-6\*][ tab]\+[A-Za-z_]\+[ tab]\+python3[ tab]\+${SUBMITTY_INSTALL_DIR}/sbin/preferred_name_logging\.php.*$~5 2 * * * submitty_daemon   python3 ${SUBMITTY_INSTALL_DIR}/sbin/preferred_name_logging.php -s submitty~" /etc/cron.d/submitty
        check_exit_code $? "Crontab"
    elif [[ $? -eq 1 ]]; then
        # Entry doesn't exist, so it will be appended at end of crontab
        # This use of sed ensures that entries are appended at the end, but not after a blank line.
        sed -ie "\$a# Run preferred_name_logging.php every night at 2:05AM
                 \$a5 2 * * * submitty_daemon   python3 ${SUBMITTY_INSTALL_DIR}/sbin/preferred_name_logging.php -s submitty" /etc/cron.d/submitty
        check_exit_code $? "Crontab"
    else
        # grep's exit status indicates an error occurred.
        echo -e "Error reading crontab.  Aborting."
        exit 1
    fi

    echo -e "Finished."
else
    # user_permission != yes
    echo -e "Cancelled."
fi
