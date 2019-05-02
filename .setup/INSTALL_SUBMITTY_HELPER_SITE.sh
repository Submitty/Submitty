#!/usr/bin/env bash

################################################################################################################
################################################################################################################
# COPY THE 1.0 Grading Website

echo -e "Copy the submission website"

THIS_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null && pwd )"
source ${THIS_DIR}/bin/versions.sh

if [ -z ${PHP_USER+x} ]; then
    # constants are not initialized,
    CONF_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"/../../../config
    SUBMITTY_REPOSITORY=$(jq -r '.submitty_repository' ${CONF_DIR}/submitty.json)
    SUBMITTY_INSTALL_DIR=$(jq -r '.submitty_install_dir' ${CONF_DIR}/submitty.json)
    PHP_USER=$(jq -r '.php_user' ${CONF_DIR}/submitty_users.json)
    PHP_GROUP=${PHP_USER}
    CGI_USER=$(jq -r '.cgi_user' ${CONF_DIR}/submitty_users.json)
    CGI_GROUP=${CGI_USER}
fi

# copy the website from the repo. We don't need the tests directory in production and then
# we don't want vendor as if it exists, it was generated locally for testing purposes, so
# we don't want it
rsync -rtz --exclude 'tests' --exclude '/site/vendor' --exclude 'site/node_modules/' ${SUBMITTY_REPOSITORY}/site   ${SUBMITTY_INSTALL_DIR}

# clear old twig cache
if [ -d "${SUBMITTY_INSTALL_DIR}/site/cache/twig" ]; then
    rm -rf "${SUBMITTY_INSTALL_DIR}/site/cache/twig"
fi
# create twig cache directory
mkdir -p ${SUBMITTY_INSTALL_DIR}/site/cache/twig

# set special user $PHP_USER as owner & group of all website files
find ${SUBMITTY_INSTALL_DIR}/site -exec chown ${PHP_USER}:${PHP_GROUP} {} \;
find ${SUBMITTY_INSTALL_DIR}/site/cgi-bin -exec chown ${CGI_USER}:${CGI_GROUP} {} \;

# set the mask for composer so that it'll run properly and be able to delete/modify
# files under it
if [ -d "${SUBMITTY_INSTALL_DIR}/site/vendor/composer" ]; then
    chmod 640 ${SUBMITTY_INSTALL_DIR}/site/composer.lock
    chmod -R 740 ${SUBMITTY_INSTALL_DIR}/site/vendor
fi

# install composer dependencies and generate classmap
su - ${PHP_USER} -c "composer install -d \"${SUBMITTY_INSTALL_DIR}/site\" --no-dev --optimize-autoloader --no-suggest"

# Install JS dependencies and then copy them into place
# We need to create the node_modules folder initially if it
# doesn't exist, or else submitty_php won't be able to make it
if [ ! -d "${SUBMITTY_INSTALL_DIR}/site/node_modules" ]; then
    mkdir -p ${SUBMITTY_INSTALL_DIR}/site/node_modules
    chown submitty_php:submitty_php ${SUBMITTY_INSTALL_DIR}/site/node_modules
fi
chmod -R 740 ${SUBMITTY_INSTALL_DIR}/site/node_modules
if [ -f "${SUBMITTY_INSTALL_DIR}/site/package-lock.json" ]; then
    chmod 640 ${SUBMITTY_INSTALL_DIR}/site/package-lock.json
fi

su - ${PHP_USER} -c "cd ${SUBMITTY_INSTALL_DIR}/site && npm install --loglevel=error"
NODE_FOLDER=${SUBMITTY_INSTALL_DIR}/site/node_modules
VENDOR_FOLDER=${SUBMITTY_INSTALL_DIR}/site/public/vendor
# clean out the old install so we don't leave anything behind
rm -rf ${VENDOR_FOLDER}
mkdir ${VENDOR_FOLDER}
# fontawesome
mkdir ${VENDOR_FOLDER}/fontawesome
mkdir ${VENDOR_FOLDER}/fontawesome/css
cp ${NODE_FOLDER}/@fortawesome/fontawesome-free/css/all.min.css ${VENDOR_FOLDER}/fontawesome/css/all.min.css
cp -R ${NODE_FOLDER}/@fortawesome/fontawesome-free/webfonts/ ${VENDOR_FOLDER}/fontawesome/
# bootstrap
cp -R ${NODE_FOLDER}/bootstrap/dist/ ${VENDOR_FOLDER}/bootstrap
# chosen.js
cp -R ${NODE_FOLDER}/chosen-js ${VENDOR_FOLDER}/chosen-js
# codemirror
mkdir ${VENDOR_FOLDER}/codemirror
mkdir ${VENDOR_FOLDER}/codemirror/theme
cp ${NODE_FOLDER}/codemirror/lib/codemirror.js ${VENDOR_FOLDER}/codemirror/
cp ${NODE_FOLDER}/codemirror/lib/codemirror.css ${VENDOR_FOLDER}/codemirror/
cp -R ${NODE_FOLDER}/codemirror/mode/ ${VENDOR_FOLDER}/codemirror
cp ${NODE_FOLDER}/codemirror/theme/monokai.css ${VENDOR_FOLDER}/codemirror/theme
cp ${NODE_FOLDER}/codemirror/theme/eclipse.css ${VENDOR_FOLDER}/codemirror/theme
# flatpickr
mkdir ${VENDOR_FOLDER}/flatpickr
cp -R ${NODE_FOLDER}/flatpickr/dist/* ${VENDOR_FOLDER}/flatpickr
# jquery
mkdir ${VENDOR_FOLDER}/jquery
cp ${NODE_FOLDER}/jquery/dist/jquery.min.* ${VENDOR_FOLDER}/jquery
# jquery.are-you-sure
mkdir ${VENDOR_FOLDER}/jquery.are-you-sure
cp ${NODE_FOLDER}/jquery.are-you-sure/jquery.are-you-sure.js ${VENDOR_FOLDER}/jquery.are-you-sure
# jquery-ui
mkdir ${VENDOR_FOLDER}/jquery-ui
cp ${NODE_FOLDER}/jquery-ui-dist/*.min.* ${VENDOR_FOLDER}/jquery-ui
cp -R ${NODE_FOLDER}/jquery-ui-dist/images ${VENDOR_FOLDER}/jquery-ui/
# jquery-ui-timepicker-addon
mkdir ${VENDOR_FOLDER}/jquery-ui-timepicker-addon
cp ${NODE_FOLDER}/jquery-ui-timepicker-addon/dist/*.min.* ${VENDOR_FOLDER}/jquery-ui-timepicker-addon
# pdfjs
mkdir ${VENDOR_FOLDER}/pdfjs
cp ${NODE_FOLDER}/pdfjs-dist/build/pdf.min.js ${VENDOR_FOLDER}/pdfjs
cp ${NODE_FOLDER}/pdfjs-dist/build/pdf.worker.min.js ${VENDOR_FOLDER}/pdfjs
cp ${NODE_FOLDER}/pdfjs-dist/web/pdf_viewer.css ${VENDOR_FOLDER}/pdfjs/pdf_viewer.css
cp ${NODE_FOLDER}/pdfjs-dist/web/pdf_viewer.js ${VENDOR_FOLDER}/pdfjs/pdf_viewer.js
# twig.js
mkdir ${VENDOR_FOLDER}/twigjs
cp ${NODE_FOLDER}/twig/twig.min.js ${VENDOR_FOLDER}/twigjs/

# TEMPORARY (until we have generalized code for generating charts in html)
# copy the zone chart images
mkdir -p ${SUBMITTY_INSTALL_DIR}/site/public/zone_images/
cp ${SUBMITTY_INSTALL_DIR}/zone_images/* ${SUBMITTY_INSTALL_DIR}/site/public/zone_images/ 2>/dev/null

#####################################
# Installing PDF annotator

mkdir -p ${SUBMITTY_INSTALL_DIR}/site/public/js/pdf
pushd ${SUBMITTY_INSTALL_DIR}/site/public/js/pdf
if [[ ! -f VERSION || $(< VERSION) != "${Pdf_Annotate_Js_Version}" ]]; then
    for b in pdf-annotate.min.js pdf-annotate.min.js.map;
        do wget -nv "https://github.com/Submitty/pdf-annotate.js/releases/download/${Pdf_Annotate_Js_Version}/${b}" -O ${b}
    done

    echo ${Pdf_Annotate_Js_Version} > VERSION
fi
popd > /dev/null

# set the permissions of all files
# $PHP_USER can read & execute all directories and read all files
# "other" can cd into all subdirectories
chmod -R 440 ${SUBMITTY_INSTALL_DIR}/site
find ${SUBMITTY_INSTALL_DIR}/site -type d -exec chmod ogu+x {} \;

# "other" can read all of these files
array=( css otf jpg png ico txt twig )
for i in "${array[@]}"; do
    find ${SUBMITTY_INSTALL_DIR}/site/public -type f -name \*.${i} -exec chmod o+r {} \;
done

#Setup Email Cron Job
crontab -u submitty_daemon -l > /tmp/cron_jobs
grep "python3 ${SUBMITTY_INSTALL_DIR}/sbin/send_email.py" /tmp/cron_jobs || echo "* * * * * python3 ${SUBMITTY_INSTALL_DIR}/sbin/send_email.py" >> /tmp/cron_jobs
crontab -u submitty_daemon -r
crontab -u submitty_daemon /tmp/cron_jobs
rm -f /tmp/cron_jobs

# Set permissions of files
# set special user $PHP_USER as owner & group of all website files
find ${SUBMITTY_INSTALL_DIR}/site -exec chown ${PHP_USER}:${PHP_GROUP} {} \;
find ${SUBMITTY_INSTALL_DIR}/site/cgi-bin -exec chown ${CGI_USER}:${CGI_GROUP} {} \;
# "other" can read & execute these files
find ${SUBMITTY_INSTALL_DIR}/site/public -type f -name \*.ttf -exec chmod o+rx {} \;
find ${SUBMITTY_INSTALL_DIR}/site/public -type f -name \*.eot -exec chmod o+rx {} \;
find ${SUBMITTY_INSTALL_DIR}/site/public -type f -name \*.svg -exec chmod o+rx {} \;
find ${SUBMITTY_INSTALL_DIR}/site/public -type f -name \*.woff -exec chmod o+rx {} \;
find ${SUBMITTY_INSTALL_DIR}/site/public -type f -name \*.woff2 -exec chmod o+rx {} \;

find ${SUBMITTY_INSTALL_DIR}/site/public -type f -name \*.js -exec chmod o+rx {} \;
find ${SUBMITTY_INSTALL_DIR}/site/cgi-bin -type f -name \*.cgi -exec chmod u+x {} \;

chmod 550 ${SUBMITTY_INSTALL_DIR}/site/cgi-bin/git-http-backend

# cache needs to be writable
find ${SUBMITTY_INSTALL_DIR}/site/cache -type d -exec chmod u+w {} \;

# return the course index page (only necessary when 'clean' option is used)
if [ -f "$mytempcurrentcourses" ]; then
    echo "return this file! ${mytempcurrentcourses} ${originalcurrentcourses}"
    mv ${mytempcurrentcourses} ${originalcurrentcourses}
fi
