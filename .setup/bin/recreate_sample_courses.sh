#!/bin/bash

# Automated regeneration of sample course data 

########################################################################

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
python3 ./.setup/bin/setup_sample_courses.py

PHP_VERSION=$(php -r 'print PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
service php${PHP_VERSION}-fpm restart
