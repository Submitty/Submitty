#!/usr/bin/env bash

################################################################################################################
################################################################################################################
# COPY THE 1.0 Grading Website

echo -e "Copy the submission website"

# copy the website from the repo
rsync -rtz --exclude 'vendor' ${SUBMITTY_REPOSITORY}/site   ${SUBMITTY_INSTALL_DIR}

# set special user $HWPHP_USER as owner & group of all website files
find ${SUBMITTY_INSTALL_DIR}/site -exec chown ${HWPHP_USER}:${HWPHP_USER} {} \;
find ${SUBMITTY_INSTALL_DIR}/site/cgi-bin -exec chown ${HWCGI_USER}:${HWCGI_USER} {} \;

# TEMPORARY (until we have generalized code for generating charts in html)
# copy the zone chart images
mkdir -p ${SUBMITTY_INSTALL_DIR}/site/public/zone_images/
cp ${SUBMITTY_INSTALL_DIR}/zone_images/* ${SUBMITTY_INSTALL_DIR}/site/public/zone_images/ 2>/dev/null

# set the permissions of all files
# $HWPHP_USER can read & execute all directories and read all files
# "other" can cd into all subdirectories
chmod -R 440 ${SUBMITTY_INSTALL_DIR}/site
find ${SUBMITTY_INSTALL_DIR}/site -type d -exec chmod ogu+x {} \;

# "other" can read all of these files
array=( css otf jpg png ico txt )
for i in "${array[@]}"; do
    find ${SUBMITTY_INSTALL_DIR}/site -type f -name \*.${i} -exec chmod o+r {} \;
done

# "other" can read & execute these files
find ${SUBMITTY_INSTALL_DIR}/site -type f -name \*.js -exec chmod o+rx {} \;
find ${SUBMITTY_INSTALL_DIR}/site -type f -name \*.cgi -exec chmod u+x {} \;

replace_fillin_variables ${SUBMITTY_INSTALL_DIR}/site/config/master_template.ini
mv ${SUBMITTY_INSTALL_DIR}/site/config/master_template.ini ${SUBMITTY_INSTALL_DIR}/site/config/master.ini

# return the course index page (only necessary when 'clean' option is used)
if [ -f "$mytempcurrentcourses" ]; then
    echo "return this file! ${mytempcurrentcourses} ${originalcurrentcourses}"
    mv ${mytempcurrentcourses} ${originalcurrentcourses}
fi
