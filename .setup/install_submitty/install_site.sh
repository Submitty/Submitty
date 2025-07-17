#!/usr/bin/env bash

### SITE INSTALLER
#
# This script is used to install the submitty site code from ${SUBMITTY_REPOSITORY}/site to
# ${SUBMITTY_INSTALL_DIR}/site. It then deals with composer and npm dependencies, and finally
# sets permissions as appropriate. To have the best efficiency and speed, we use rsync to
# deal with moving the files, and then use the result of that command to tell us what files
# were updated and to accomplish the following just for those files:
#   - if package.json or package-lock.json was modified, run NPM and move into place js files
#   - if composer.json or composer.lock was modified, run composer
#   - only set permissions for modified files
#
# This script has two command-line arguments:
#   Required argument: config=<config dir>.
#   Optional argument: browscap

set_permissions () {
    local fullpath=$1
    filename=$(basename -- "$fullpath")
    extension="${filename##*.}"
    # filename="${filename%.*}"
    case "${extension}" in
        css|otf|jpg|png|mp3|ico|txt|twig|map)
            chmod 444 ${fullpath}
            ;;
        bcmap|ttf|eot|svg|woff|woff2|js|mjs|cgi)
            chmod 445 ${fullpath}
            ;;
        html)
            if [ ${fullpath} != ${SUBMITTY_INSTALL_DIR}/site/public/index.html ]; then
                chmod 440 ${fullpath}
            fi
            ;;
        *)
            chmod 440 ${fullpath}
            ;;
    esac
}

set_mjs_permission () {
    for file in $1/*; do
        if [ -d "$file" ]; then
            chmod 551 $file
            set_mjs_permission $file
        else
            set_permissions $file
        fi
    done
}

echo -e "Copy the submission website"

THIS_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null && pwd )"
source ${THIS_DIR}/../bin/versions.sh

# This is run under /usr/local/submitty/GIT_CHECKOUT/Submitty/.setup/bin/
CONF_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"/../../../../config
SUBMITTY_REPOSITORY=$(jq -r '.submitty_repository' ${CONF_DIR}/submitty.json)
SUBMITTY_INSTALL_DIR=$(jq -r '.submitty_install_dir' ${CONF_DIR}/submitty.json)
SUBMITTY_DATA_DIR=$(jq -r '.submitty_data_dir' ${SUBMITTY_INSTALL_DIR}/config/submitty.json)
DEBUG_MODE=$(jq -r '.debugging_enabled' ${SUBMITTY_INSTALL_DIR}/config/database.json)
PHP_USER=$(jq -r '.php_user' ${CONF_DIR}/submitty_users.json)
PHP_GROUP=${PHP_USER}
CGI_USER=$(jq -r '.cgi_user' ${CONF_DIR}/submitty_users.json)
CGI_GROUP=${CGI_USER}
VAGRANT=0

if [ -d "${THIS_DIR}/../../.vagrant" ]; then
    VAGRANT=1
fi

# Get arguments
for cli_arg in "$@"
do
    if [[ $cli_arg =~ ^config=.* ]]; then
        SUBMITTY_CONFIG_DIR="$(readlink -f "$(echo "$cli_arg" | cut -f2 -d=)")"
    elif [ "$cli_arg" == "browscap" ]; then
        BROWSCAP=true
    elif [[ $cli_arg == "skip-vendor" ]]; then
        SKIP_VENDOR=true
    elif [[ $cli_arg == "skip-node" ]]; then
        SKIP_NODE=true
    elif [[ $cli_arg == "skip-npm-build" ]]; then
        SKIP_NPM_BUILD=true
    fi
done

if [ -z "${SUBMITTY_CONFIG_DIR}" ]; then
    echo "ERROR: This script requires a config dir argument"
    echo "Usage: ${BASH_SOURCE[0]} config=<config dir> [browscap]"
    exit 1
fi

SUBMITTY_REPOSITORY=$(jq -r '.submitty_repository' ${SUBMITTY_CONFIG_DIR:?}/submitty.json)
source ${SUBMITTY_REPOSITORY:?}/.setup/install_submitty/get_globals.sh "config=${SUBMITTY_CONFIG_DIR:?}"
source ${SUBMITTY_REPOSITORY:?}/.setup/bin/versions.sh

mkdir -p ${SUBMITTY_INSTALL_DIR}/site/public

pushd /tmp > /dev/null
# Make a temporary directory that root will own so there is no risk of
# index.html existing before hand and causing issues
TMP_DIR=$(mktemp -d)

echo "Submitty is being updated. Please try again in 2 minutes." > ${TMP_DIR}/index.html
chmod 644 ${TMP_DIR}/index.html
chown ${CGI_USER}:${CGI_GROUP} ${TMP_DIR}/index.html
mv ${TMP_DIR}/index.html ${SUBMITTY_INSTALL_DIR}/site/public

popd > /dev/null
rm -rf ${TMP_DIR}

if [ ! -d "${SUBMITTY_DATA_DIR}/run/websocket" ]; then
    mkdir -p ${SUBMITTY_DATA_DIR}/run/websocket
fi

chown root:root ${SUBMITTY_DATA_DIR}/run
chmod 755 ${SUBMITTY_DATA_DIR}/run

chown ${PHP_USER}:www-data ${SUBMITTY_DATA_DIR}/run/websocket
chmod 2750 ${SUBMITTY_DATA_DIR}/run/websocket

# Delete all typescript code to prevent deleted files being left behind and potentially
# causing compilation errors
if [ -d "${SUBMITTY_INSTALL_DIR}/site/ts" ]; then
    rm -r "${SUBMITTY_INSTALL_DIR}/site/ts"
fi

# Delete all vue code to prevent deleted vue files from being rendered
if [ -d "${SUBMITTY_INSTALL_DIR}/site/vue" ]; then
    rm -r "${SUBMITTY_INSTALL_DIR}/site/vue"
fi

# copy the website from the repo. We don't need the tests directory in production and then
# we don't want vendor as if it exists, it was generated locally for testing purposes, so
# we don't want it
result=$(rsync -rtz -i --exclude-from ${SUBMITTY_REPOSITORY}/site/.rsyncignore ${SUBMITTY_REPOSITORY}/site ${SUBMITTY_INSTALL_DIR})
if [ ${VAGRANT} == 1 ]; then
    rsync -rtz -i ${SUBMITTY_REPOSITORY}/site/tests ${SUBMITTY_REPOSITORY}/site/phpunit.xml ${SUBMITTY_INSTALL_DIR}/site > /dev/null
    chown -R ${PHP_USER}:${PHP_GROUP} ${SUBMITTY_INSTALL_DIR}/site/tests
    chmod -R 740 ${SUBMITTY_INSTALL_DIR}/site/tests
    chown ${PHP_USER}:${PHP_GROUP} ${SUBMITTY_INSTALL_DIR}/site/phpunit.xml
    chmod 740 ${SUBMITTY_INSTALL_DIR}/site/phpunit.xml
fi

# check for either of the dependency folders, and if they do not exist, pretend like
# their respective json file was edited. Composer needs the folder to exist to even
# run, but it could be someone just deleted the folders to try and fix some
# weird dependency installation issue (common with npm troubleshooting).
if [ ! -d ${SUBMITTY_INSTALL_DIR}/site/vendor ]; then
    mkdir ${SUBMITTY_INSTALL_DIR}/site/vendor
    chown -R ${PHP_USER}:${PHP_USER} ${SUBMITTY_INSTALL_DIR}/site/vendor
    result=$(echo -e "${result}\n>f+++++++++ site/composer.json")
fi

if [ ! -d ${SUBMITTY_INSTALL_DIR}/site/node_modules ]; then
    result=$(echo -e "${result}\n>f+++++++++ site/package.json")
fi

readarray -t result_array <<< "${result}"

# clear old twig cache
if [ -d "${SUBMITTY_INSTALL_DIR}/site/cache/twig" ]; then
    rm -rf "${SUBMITTY_INSTALL_DIR}/site/cache/twig"
fi
# create twig cache directory
mkdir -p ${SUBMITTY_INSTALL_DIR}/site/cache/twig

# clear old routes cache
if [ -d "${SUBMITTY_INSTALL_DIR}/site/cache/routes" ]; then
    rm -rf "${SUBMITTY_INSTALL_DIR}/site/cache/routes"
fi
# create routes cache directory
mkdir -p ${SUBMITTY_INSTALL_DIR}/site/cache/routes

# clear old doctrine cache
if [ -d "${SUBMITTY_INSTALL_DIR}/site/cache/doctrine" ]; then
    rm -rf "${SUBMITTY_INSTALL_DIR}/site/cache/doctrine"
fi
# create doctrine cache directory
mkdir -p ${SUBMITTY_INSTALL_DIR}/site/cache/doctrine

# clear old access control cache
if [ -d "${SUBMITTY_INSTALL_DIR}/site/cache/access_control" ]; then
    rm -rf "${SUBMITTY_INSTALL_DIR}/site/cache/access_control"
fi
# create access control cache directory
mkdir -p ${SUBMITTY_INSTALL_DIR}/site/cache/access_control

# clear old doctrine proxy classes
if [ -d "${SUBMITTY_INSTALL_DIR}/site/cache/doctrine-proxy" ]; then
    rm -rf "${SUBMITTY_INSTALL_DIR}/site/cache/doctrine-proxy"
fi
# create doctrine proxy classes directory
mkdir -p ${SUBMITTY_INSTALL_DIR}/site/cache/doctrine-proxy

# clear old lang cache
if [ -d "${SUBMITTY_INSTALL_DIR}/site/cache/lang" ]; then
    rm -rf "${SUBMITTY_INSTALL_DIR}/site/cache/lang"
fi
# create lang cache directory
mkdir -p ${SUBMITTY_INSTALL_DIR}/site/cache/lang

if [ -d "${SUBMITTY_INSTALL_DIR}/site/public/mjs" ]; then
    rm -r "${SUBMITTY_INSTALL_DIR}/site/public/mjs"
fi
# create output dir for esbuild
mkdir -p ${SUBMITTY_INSTALL_DIR}/site/public/mjs

# Update ownership to PHP_USER for affected files and folders
chown ${PHP_USER}:${PHP_GROUP} ${SUBMITTY_INSTALL_DIR}/site
for entry in "${result_array[@]}"; do
    chown ${PHP_USER}:${PHP_GROUP} "${SUBMITTY_INSTALL_DIR}/${entry:12}"
done

# Update ownership for cgi-bin to CGI_USER
find ${SUBMITTY_INSTALL_DIR}/site/cgi-bin -exec chown ${CGI_USER}:${CGI_GROUP} {} \;

chown ${PHP_USER}:${PHP_GROUP} ${SUBMITTY_INSTALL_DIR}/site/public/mjs

# set the mask for composer so that it'll run properly and be able to delete/modify
# files under it
if [ -d "${SUBMITTY_INSTALL_DIR}/site/vendor/composer" ]; then
    chmod 640 ${SUBMITTY_INSTALL_DIR}/site/composer.lock
    chmod -R 740 ${SUBMITTY_INSTALL_DIR}/site/vendor
fi

# Set proper read/execute for "other" on files with certain extensions
# so apache can properly handle them
echo "Set permissions"
for entry in "${result_array[@]}"; do
    if echo ${entry} | grep -E -q "^.d"; then
        if [ ! -z "${entry}" ]; then
            chmod 551 ${SUBMITTY_INSTALL_DIR}/${entry:12}
        fi
    elif echo ${entry} | grep -E -q "site/public"; then
        if [ ! -z "${entry}" ]; then
            set_permissions "${SUBMITTY_INSTALL_DIR}/${entry:12}"
        fi
    elif echo ${entry} | grep -E -q "^.f"; then
        if [ ! -z "${entry}" ]; then
            chmod 440 ${SUBMITTY_INSTALL_DIR}/${entry:12}
        fi
    fi
done

if [ -z "${SKIP_VENDOR}" ]; then
    # install composer dependencies and generate classmap
    if [ ${VAGRANT} == 1 ]; then
        su - ${PHP_USER} -c "composer install -d \"${SUBMITTY_INSTALL_DIR}/site\" --dev --prefer-dist --optimize-autoloader"
    else
        su - ${PHP_USER} -c "composer install -d \"${SUBMITTY_INSTALL_DIR}/site\" --no-dev --prefer-dist --optimize-autoloader"
    fi
    chown -R ${PHP_USER}:${PHP_USER} ${SUBMITTY_INSTALL_DIR}/site/vendor

    find ${SUBMITTY_INSTALL_DIR}/site/vendor -type d -exec chmod 551 {} \;
    find ${SUBMITTY_INSTALL_DIR}/site/vendor -type f -exec chmod 440 {} \;
fi

# create doctrine proxy classes
if [ $DEBUG_MODE != true ]; then
    php "${SUBMITTY_INSTALL_DIR}/sbin/doctrine.php" "orm:generate-proxies"
fi

# load lang files
php "${SUBMITTY_INSTALL_DIR}/sbin/load_lang.php" "${SUBMITTY_REPOSITORY}/../Localization/lang" "${SUBMITTY_INSTALL_DIR}/site/cache/lang"

# Update permissions & ownership for cache directory
chmod -R 751 ${SUBMITTY_INSTALL_DIR}/site/cache
chown -R ${PHP_USER}:${PHP_GROUP} ${SUBMITTY_INSTALL_DIR}/site/cache

if [[ "${CI}" != true && "${BROWSCAP}" = true ]]; then
    echo -e "Checking for and fetching latest browscap.ini if needed"
    # browscap.ini is needed for users' browser identification, this information is shown on session management page
    # fetch and convert browscap.ini to cache, may take some time on initial setup or if there's an update
    ${SUBMITTY_INSTALL_DIR}/sbin/update_browscap.php
    chown -R ${PHP_USER}:${PHP_USER} ${SUBMITTY_INSTALL_DIR}/site/vendor/browscap/browscap-php/resources
fi

NODE_FOLDER=${SUBMITTY_INSTALL_DIR}/site/node_modules

chmod 440 ${SUBMITTY_INSTALL_DIR}/site/composer.lock

if [[ -z "${SKIP_NODE}" ]]; then
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

    su - ${PHP_USER} -c "cd ${SUBMITTY_INSTALL_DIR}/site && npm install --loglevel=error --no-save"

    VENDOR_FOLDER=${SUBMITTY_INSTALL_DIR}/site/public/vendor

    chown -R ${PHP_USER}:${PHP_USER} ${NODE_FOLDER}

    echo "Copy NPM packages into place"
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
    cp -R ${NODE_FOLDER}/codemirror/addon ${VENDOR_FOLDER}/codemirror/addon
    # codemirror-spell-checker
    mkdir ${VENDOR_FOLDER}/codemirror-spell-checker
    cp ${NODE_FOLDER}/codemirror-spell-checker/dist/spell-checker.min.js ${VENDOR_FOLDER}/codemirror-spell-checker
    cp ${NODE_FOLDER}/codemirror-spell-checker/dist/spell-checker.min.css ${VENDOR_FOLDER}/codemirror-spell-checker
    #codemirror6
    mkdir ${VENDOR_FOLDER}/codemirror6
    mkdir ${VENDOR_FOLDER}/codemirror6/view
    mkdir ${VENDOR_FOLDER}/codemirror6/state
    mkdir ${VENDOR_FOLDER}/codemirror6/commands
    mkdir ${VENDOR_FOLDER}/codemirror6/language
    mkdir ${VENDOR_FOLDER}/codemirror6/autocomplete
    cp -R ${NODE_FOLDER}/@codemirror/view/dist ${VENDOR_FOLDER}/codemirror6/view
    cp -R ${NODE_FOLDER}/@codemirror/state/dist ${VENDOR_FOLDER}/codemirror6/state
    cp -R ${NODE_FOLDER}/@codemirror/commands/dist ${VENDOR_FOLDER}/codemirror6/commands
    cp -R ${NODE_FOLDER}/@codemirror/language/dist ${VENDOR_FOLDER}/codemirror6/language
    cp -R ${NODE_FOLDER}/@codemirror/autocomplete/dist ${VENDOR_FOLDER}/codemirror6/autocomplete
    # flatpickr
    mkdir ${VENDOR_FOLDER}/flatpickr
    cp -R ${NODE_FOLDER}/flatpickr/dist/* ${VENDOR_FOLDER}/flatpickr
    # select2
    mkdir ${VENDOR_FOLDER}/select2
    cp -R ${NODE_FOLDER}/select2/dist/* ${VENDOR_FOLDER}/select2
    # select2-theme-bootstrap5
    mkdir ${VENDOR_FOLDER}/select2/bootstrap5-theme
    cp -R ${NODE_FOLDER}/select2-bootstrap-5-theme/dist/select2-bootstrap-5-theme.min.css ${VENDOR_FOLDER}/select2/bootstrap5-theme
    # shortcut-buttons-flatpickr
    mkdir ${VENDOR_FOLDER}/flatpickr/plugins/shortcutButtons
    cp -R ${NODE_FOLDER}/shortcut-buttons-flatpickr/dist/* ${VENDOR_FOLDER}/flatpickr/plugins/shortcutButtons
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
    #luxon
    mkdir ${VENDOR_FOLDER}/luxon
    cp ${NODE_FOLDER}/luxon/build/global/luxon.min.js ${VENDOR_FOLDER}/luxon
    # pdfjs
    mkdir ${VENDOR_FOLDER}/pdfjs
    cp -R ${NODE_FOLDER}/pdfjs-dist/build/* ${VENDOR_FOLDER}/pdfjs
    cp ${NODE_FOLDER}/pdfjs-dist/web/pdf_viewer.mjs ${VENDOR_FOLDER}/pdfjs
    cp ${NODE_FOLDER}/pdfjs-dist/web/pdf_viewer.css ${VENDOR_FOLDER}/pdfjs
    cp -R ${NODE_FOLDER}/pdfjs-dist/cmaps ${VENDOR_FOLDER}/pdfjs
    # plotly
    mkdir ${VENDOR_FOLDER}/plotly
    cp ${NODE_FOLDER}/plotly.js-dist/plotly.js ${VENDOR_FOLDER}/plotly
    # mermaid
    mkdir ${VENDOR_FOLDER}/mermaid
    cp ${NODE_FOLDER}/mermaid/dist/*.min.* ${VENDOR_FOLDER}/mermaid
    # twig.js
    mkdir ${VENDOR_FOLDER}/twigjs
    cp ${NODE_FOLDER}/twig/twig.min.js ${VENDOR_FOLDER}/twigjs/
    # jspdf
    mkdir ${VENDOR_FOLDER}/jspdf
    cp ${NODE_FOLDER}/jspdf/dist/jspdf.umd.min.js ${VENDOR_FOLDER}/jspdf/jspdf.min.js
    cp ${NODE_FOLDER}/jspdf/dist/jspdf.umd.min.js.map ${VENDOR_FOLDER}/jspdf/jspdf.min.js.map
    # highlight.js
    mkdir ${VENDOR_FOLDER}/highlight.js
    cp ${NODE_FOLDER}/@highlightjs/cdn-assets/highlight.min.js ${VENDOR_FOLDER}/highlight.js/
    # js-cookie
    mkdir ${VENDOR_FOLDER}/js-cookie
    cp ${NODE_FOLDER}/js-cookie/dist/js.cookie.min.js ${VENDOR_FOLDER}/js-cookie
    #vue
    mkdir ${VENDOR_FOLDER}/vue
    cp ${NODE_FOLDER}/vue/dist/vue.runtime.global.prod.js ${VENDOR_FOLDER}/vue

    find ${NODE_FOLDER} -type d -exec chmod 551 {} \;
    find ${NODE_FOLDER} -type f -exec chmod 440 {} \;
    find ${VENDOR_FOLDER} -type d -exec chmod 551 {} \;
    find ${VENDOR_FOLDER} -type f | while read file; do set_permissions "$file"; done
fi

chmod 440 ${SUBMITTY_INSTALL_DIR}/site/package-lock.json
# Permissions for PWA
chmod 444 ${SUBMITTY_INSTALL_DIR}/site/public/manifest.json

# Set cgi-bin permissions
chown -R ${CGI_USER}:${CGI_USER} ${SUBMITTY_INSTALL_DIR}/site/cgi-bin
chmod 540 ${SUBMITTY_INSTALL_DIR}/site/cgi-bin/*
chmod 550 ${SUBMITTY_INSTALL_DIR}/site/cgi-bin/git-http-backend

mkdir -p "${SUBMITTY_INSTALL_DIR}/site/incremental_build"
chgrp "${PHP_USER}" "${SUBMITTY_INSTALL_DIR}/site/incremental_build"

if [[ -z "${SKIP_NPM_BUILD}" ]]; then
    echo "Running esbuild"
    chmod a+x ${NODE_FOLDER}/esbuild/bin/esbuild
    chmod a+x ${NODE_FOLDER}/typescript/bin/tsc
    chmod a+x ${NODE_FOLDER}/vite/bin/vite.js
    chmod g+w "${SUBMITTY_INSTALL_DIR}/site/incremental_build"
    chmod -R u+w "${SUBMITTY_INSTALL_DIR}/site/incremental_build"
    chmod +w "${SUBMITTY_INSTALL_DIR}/site/vue"
    su - ${PHP_USER} -c "cd ${SUBMITTY_INSTALL_DIR}/site && npm run build"
    chmod -w "${SUBMITTY_INSTALL_DIR}/site/vue"
    chmod a-x ${NODE_FOLDER}/esbuild/bin/esbuild
    chmod a-x ${NODE_FOLDER}/typescript/bin/tsc
    chmod g-w "${SUBMITTY_INSTALL_DIR}/site/incremental_build"
    chmod a-x ${NODE_FOLDER}/vite/bin/vite.js
    chmod -R u-w "${SUBMITTY_INSTALL_DIR}/site/incremental_build"

    chmod 551 ${SUBMITTY_INSTALL_DIR}/site/public/mjs
    set_mjs_permission ${SUBMITTY_INSTALL_DIR}/site/public/mjs
fi

# cache needs to be writable
find ${SUBMITTY_INSTALL_DIR}/site/cache -type d -exec chmod u+w {} \;

# reload PHP-FPM before we re-enable website, but only if PHP-FPM is actually being used
# as expected (Travis for example will fail here otherwise).
PHP_VERSION=$(php -r 'print PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
set +e
systemctl is-active --quiet php${PHP_VERSION}-fpm
if [[ "$?" == "0" ]]; then
    systemctl reload php${PHP_VERSION}-fpm
fi
set -e

rm -f ${SUBMITTY_INSTALL_DIR}/site/public/index.html
