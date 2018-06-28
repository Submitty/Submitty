#!/usr/bin/env bash

################################################################################################################
################################################################################################################
# COPY THE 1.0 Grading Website

echo -e "Copy the submission website"

if [ -z ${SUBMITTY_INSTALL_DIR+x} ]; then
    # constants are not initialized,
    CONF_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"/../../../config
    SUBMITTY_REPOSITORY=$(jq -r '.submitty_repository' ${CONF_DIR}/submitty.json)
    SUBMITTY_INSTALL_DIR=$(jq -r '.submitty_install_dir' ${CONF_DIR}/submitty.json)
    SUBMITTY_PHP_USER=$(jq -r '.submitty_php_user' ${CONF_DIR}/submitty_users.json)
    SUBMITTY_CGI_USER=$(jq -r '.submitty_cgi_user' ${CONF_DIR}/submitty_users.json)
fi

# copy the website from the repo
rsync -rtz --exclude 'tests' ${SUBMITTY_REPOSITORY}/site   ${SUBMITTY_INSTALL_DIR}

# set special user $SUBMITTY_PHP_USER as owner & group of all website files
find ${SUBMITTY_INSTALL_DIR}/site -exec chown ${SUBMITTY_PHP_USER}:${SUBMITTY_PHP_USER} {} \;
find ${SUBMITTY_INSTALL_DIR}/site/cgi-bin -exec chown ${SUBMITTY_CGI_USER}:${SUBMITTY_CGI_USER} {} \;

# set these masks just for when composer to run, and then they can be set to whatever
if [ -d "${SUBMITTY_INSTALL_DIR}/site/vendor/composer" ]; then
    chmod 640 ${SUBMITTY_INSTALL_DIR}/site/composer.lock
    chmod 640 ${SUBMITTY_INSTALL_DIR}/site/vendor/autoload.php
    chmod 640 ${SUBMITTY_INSTALL_DIR}/site/vendor/composer/*
fi

# install composer dependencies and generate classmap
su - ${SUBMITTY_PHP_USER} -c "composer install -d \"${SUBMITTY_INSTALL_DIR}/site\" --no-dev --optimize-autoloader"

# TEMPORARY (until we have generalized code for generating charts in html)
# copy the zone chart images
mkdir -p ${SUBMITTY_INSTALL_DIR}/site/public/zone_images/
cp ${SUBMITTY_INSTALL_DIR}/zone_images/* ${SUBMITTY_INSTALL_DIR}/site/public/zone_images/ 2>/dev/null

# set the permissions of all files
# $SUBMITTY_PHP_USER can read & execute all directories and read all files
# "other" can cd into all subdirectories
chmod -R 440 ${SUBMITTY_INSTALL_DIR}/site
find ${SUBMITTY_INSTALL_DIR}/site -type d -exec chmod ogu+x {} \;

# "other" can read all of these files
array=( css otf jpg png ico txt twig )
for i in "${array[@]}"; do
    find ${SUBMITTY_INSTALL_DIR}/site/public -type f -name \*.${i} -exec chmod o+r {} \;
done

# "other" can read & execute these files
find ${SUBMITTY_INSTALL_DIR}/site/public -type f -name \*.js -exec chmod o+rx {} \;
find ${SUBMITTY_INSTALL_DIR}/site/cgi-bin -type f -name \*.cgi -exec chmod u+x {} \;

# return the course index page (only necessary when 'clean' option is used)
if [ -f "$mytempcurrentcourses" ]; then
    echo "return this file! ${mytempcurrentcourses} ${originalcurrentcourses}"
    mv ${mytempcurrentcourses} ${originalcurrentcourses}
fi
