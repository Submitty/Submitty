#!/usr/bin/env bash

set_permissions () {
    local fullpath=$1
    filename=$(basename -- "$fullpath")
    extension="${filename##*.}"
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

THIS_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null && pwd )"
source ${THIS_DIR}/../bin/versions.sh

CONF_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"/../../../../config
SUBMITTY_REPOSITORY=$(jq -r '.submitty_repository' ${CONF_DIR}/submitty.json)
SUBMITTY_INSTALL_DIR=$(jq -r '.submitty_install_dir' ${CONF_DIR}/submitty.json)
PHP_USER=$(jq -r '.php_user' ${CONF_DIR}/submitty_users.json)
PHP_GROUP=${PHP_USER}
CGI_USER=$(jq -r '.cgi_user' ${CONF_DIR}/submitty_users.json)
CGI_GROUP=${CGI_USER}

for cli_arg in "$@"
do
    if [[ $cli_arg =~ ^config=.* ]]; then
        SUBMITTY_CONFIG_DIR="$(readlink -f "$(echo "$cli_arg" | cut -f2 -d=)")"
    elif [ "$cli_arg" == "browscap" ]; then
        BROWSCAP=true
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

# Read rsync result written by copy_site.sh
if [ -f /tmp/submitty_site_rsync_result ]; then
    result=$(cat /tmp/submitty_site_rsync_result)
else
    result=""
fi

readarray -t result_array <<< "${result}"

# Composer handling
if echo "${result}" | grep -E -q "composer\.(json|lock)"; then
    if [ -d "${SUBMITTY_INSTALL_DIR}/site/vendor" ]; then
        if [ ${VAGRANT:-0} == 1 ]; then
            su - ${PHP_USER} -c "composer install -d \"${SUBMITTY_INSTALL_DIR}/site\" --dev --prefer-dist --optimize-autoloader"
        else
            su - ${PHP_USER} -c "composer install -d \"${SUBMITTY_INSTALL_DIR}/site\" --no-dev --prefer-dist --optimize-autoloader"
        fi
        chown -R ${PHP_USER}:${PHP_USER} ${SUBMITTY_INSTALL_DIR}/site/vendor
    fi
else
    if [ ${VAGRANT:-0} == 1 ]; then
        su - ${PHP_USER} -c "composer dump-autoload -d \"${SUBMITTY_INSTALL_DIR}/site\" --optimize"
    else
        su - ${PHP_USER} -c "composer dump-autoload -d \"${SUBMITTY_INSTALL_DIR}/site\" --optimize --no-dev"
    fi
    chown -R ${PHP_USER}:${PHP_USER} ${SUBMITTY_INSTALL_DIR}/site/vendor/composer
fi

find ${SUBMITTY_INSTALL_DIR}/site/vendor -type d -exec chmod 551 {} \;
find ${SUBMITTY_INSTALL_DIR}/site/vendor -type f -exec chmod 440 {} \;

# create doctrine proxy classes and load lang files
php "${SUBMITTY_INSTALL_DIR}/sbin/doctrine.php" "orm:generate-proxies"
php "${SUBMITTY_INSTALL_DIR}/sbin/load_lang.php" "${SUBMITTY_REPOSITORY}/../Localization/lang" "${SUBMITTY_INSTALL_DIR}/site/cache/lang"

chmod -R 751 ${SUBMITTY_INSTALL_DIR}/site/cache
chown -R ${PHP_USER}:${PHP_GROUP} ${SUBMITTY_INSTALL_DIR}/site/cache

if [[ "${CI}" != true && "${BROWSCAP}" = true ]]; then
    echo -e "Checking for and fetching latest browscap.ini if needed"
    ${SUBMITTY_INSTALL_DIR}/sbin/update_browscap.php
    chown -R ${PHP_USER}:${PHP_USER} ${SUBMITTY_INSTALL_DIR}/site/vendor/browscap/browscap-php/resources
fi

NODE_FOLDER=${SUBMITTY_INSTALL_DIR}/site/node_modules

chmod 440 ${SUBMITTY_INSTALL_DIR}/site/composer.lock || true

if echo "${result}" | grep -E -q "package(-lock)?.json"; then
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
    rm -rf ${VENDOR_FOLDER}
    mkdir ${VENDOR_FOLDER}
    mkdir ${VENDOR_FOLDER}/fontawesome
    mkdir ${VENDOR_FOLDER}/fontawesome/css
    cp ${NODE_FOLDER}/@fortawesome/fontawesome-free/css/all.min.css ${VENDOR_FOLDER}/fontawesome/css/all.min.css
    cp -R ${NODE_FOLDER}/@fortawesome/fontawesome-free/webfonts/ ${VENDOR_FOLDER}/fontawesome/
    cp -R ${NODE_FOLDER}/bootstrap/dist/ ${VENDOR_FOLDER}/bootstrap
    cp -R ${NODE_FOLDER}/chosen-js ${VENDOR_FOLDER}/chosen-js
    mkdir ${VENDOR_FOLDER}/codemirror
    mkdir ${VENDOR_FOLDER}/codemirror/theme
    cp ${NODE_FOLDER}/codemirror/lib/codemirror.js ${VENDOR_FOLDER}/codemirror/
    cp ${NODE_FOLDER}/codemirror/lib/codemirror.css ${VENDOR_FOLDER}/codemirror/
    cp -R ${NODE_FOLDER}/codemirror/mode/ ${VENDOR_FOLDER}/codemirror
    cp ${NODE_FOLDER}/codemirror/theme/monokai.css ${VENDOR_FOLDER}/codemirror/theme
    cp ${NODE_FOLDER}/codemirror/theme/eclipse.css ${VENDOR_FOLDER}/codemirror/theme
    cp -R ${NODE_FOLDER}/codemirror/addon ${VENDOR_FOLDER}/codemirror/addon
    mkdir ${VENDOR_FOLDER}/codemirror-spell-checker
    cp ${NODE_FOLDER}/codemirror-spell-checker/dist/spell-checker.min.js ${VENDOR_FOLDER}/codemirror-spell-checker
    cp ${NODE_FOLDER}/codemirror-spell-checker/dist/spell-checker.min.css ${VENDOR_FOLDER}/codemirror-spell-checker
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
    mkdir ${VENDOR_FOLDER}/flatpickr
    cp -R ${NODE_FOLDER}/flatpickr/dist/* ${VENDOR_FOLDER}/flatpickr
    mkdir ${VENDOR_FOLDER}/select2
    cp -R ${NODE_FOLDER}/select2/dist/* ${VENDOR_FOLDER}/select2
    mkdir ${VENDOR_FOLDER}/select2/bootstrap5-theme
    cp -R ${NODE_FOLDER}/select2-bootstrap-5-theme/dist/select2-bootstrap-5-theme.min.css ${VENDOR_FOLDER}/select2/bootstrap5-theme
    mkdir ${VENDOR_FOLDER}/flatpickr/plugins/shortcutButtons
    cp -R ${NODE_FOLDER}/shortcut-buttons-flatpickr/dist/* ${VENDOR_FOLDER}/flatpickr/plugins/shortcutButtons
    mkdir ${VENDOR_FOLDER}/jquery
    cp ${NODE_FOLDER}/jquery/dist/jquery.min.* ${VENDOR_FOLDER}/jquery
    mkdir ${VENDOR_FOLDER}/jquery.are-you-sure
    cp ${NODE_FOLDER}/jquery.are-you-sure/jquery.are-you-sure.js ${VENDOR_FOLDER}/jquery.are-you-sure
    mkdir ${VENDOR_FOLDER}/jquery-ui
    cp ${NODE_FOLDER}/jquery-ui-dist/*.min.* ${VENDOR_FOLDER}/jquery-ui
    cp -R ${NODE_FOLDER}/jquery-ui-dist/images ${VENDOR_FOLDER}/jquery-ui/
    mkdir ${VENDOR_FOLDER}/luxon
    cp ${NODE_FOLDER}/luxon/build/global/luxon.min.js ${VENDOR_FOLDER}/luxon
    mkdir ${VENDOR_FOLDER}/pdfjs
    cp -R ${NODE_FOLDER}/pdfjs-dist/build/* ${VENDOR_FOLDER}/pdfjs
    cp ${NODE_FOLDER}/pdfjs-dist/web/pdf_viewer.mjs ${VENDOR_FOLDER}/pdfjs
    cp ${NODE_FOLDER}/pdfjs-dist/web/pdf_viewer.css ${VENDOR_FOLDER}/pdfjs
    cp -R ${NODE_FOLDER}/pdfjs-dist/cmaps ${VENDOR_FOLDER}/pdfjs
    mkdir ${VENDOR_FOLDER}/plotly
    cp ${NODE_FOLDER}/plotly.js-dist/plotly.js ${VENDOR_FOLDER}/plotly
    mkdir ${VENDOR_FOLDER}/mermaid
    cp ${NODE_FOLDER}/mermaid/dist/*.min.* ${VENDOR_FOLDER}/mermaid
    mkdir ${VENDOR_FOLDER}/twigjs
    cp ${NODE_FOLDER}/twig/twig.min.js ${VENDOR_FOLDER}/twigjs/
    mkdir ${VENDOR_FOLDER}/jspdf
    cp ${NODE_FOLDER}/jspdf/dist/jspdf.umd.min.js ${VENDOR_FOLDER}/jspdf/jspdf.min.js
    cp ${NODE_FOLDER}/jspdf/dist/jspdf.umd.min.js.map ${VENDOR_FOLDER}/jspdf/jspdf.min.js.map
    mkdir ${VENDOR_FOLDER}/highlight.js
    cp ${NODE_FOLDER}/@highlightjs/cdn-assets/highlight.min.js ${VENDOR_FOLDER}/highlight.js/
    mkdir ${VENDOR_FOLDER}/js-cookie
    cp ${NODE_FOLDER}/js-cookie/dist/js.cookie.min.js ${VENDOR_FOLDER}/js-cookie
    mkdir ${VENDOR_FOLDER}/vue
    cp ${NODE_FOLDER}/vue/dist/vue.runtime.global.prod.js ${VENDOR_FOLDER}/vue
    mkdir -p ${VENDOR_FOLDER}/katex/fonts
    cp ${NODE_FOLDER}/katex/dist/katex.min.css ${VENDOR_FOLDER}/katex
    cp ${NODE_FOLDER}/katex/dist/fonts/*.woff2 ${VENDOR_FOLDER}/katex/fonts

    find ${NODE_FOLDER} -type d -exec chmod 551 {} \;
    find ${NODE_FOLDER} -type f -exec chmod 440 {} \;
    find ${VENDOR_FOLDER} -type d -exec chmod 551 {} \;
    find ${VENDOR_FOLDER} -type f | while read file; do set_permissions "$file"; done
fi

chmod 440 ${SUBMITTY_INSTALL_DIR}/site/package-lock.json || true
chmod 444 ${SUBMITTY_INSTALL_DIR}/site/public/manifest.json || true

chown -R ${CGI_USER}:${CGI_USER} ${SUBMITTY_INSTALL_DIR}/site/cgi-bin || true
chmod 540 ${SUBMITTY_INSTALL_DIR}/site/cgi-bin/* || true
chmod 550 ${SUBMITTY_INSTALL_DIR}/site/cgi-bin/git-http-backend || true

mkdir -p "${NODE_FOLDER}/.vue-global-types"
chown -R "${PHP_USER}:${PHP_USER}" "${NODE_FOLDER}/.vue-global-types"
mkdir -p "${SUBMITTY_INSTALL_DIR}/site/incremental_build"
chgrp "${PHP_USER}" "${SUBMITTY_INSTALL_DIR}/site/incremental_build"

echo "Running esbuild"
chmod a+x ${NODE_FOLDER}/esbuild/bin/esbuild || true
chmod a+x ${NODE_FOLDER}/typescript/bin/tsc || true
chmod a+x ${NODE_FOLDER}/vue-tsc/bin/vue-tsc.js || true
chmod -R u+rw ${NODE_FOLDER}/.vue-global-types || true
chmod a+x ${NODE_FOLDER}/vite/bin/vite.js || true
chmod g+w "${SUBMITTY_INSTALL_DIR}/site/incremental_build" || true
chmod -R u+w "${SUBMITTY_INSTALL_DIR}/site/incremental_build" || true
chmod +w "${SUBMITTY_INSTALL_DIR}/site/vue" || true
su - ${PHP_USER} -c "cd ${SUBMITTY_INSTALL_DIR}/site && npm run build" || true
chmod -w "${SUBMITTY_INSTALL_DIR}/site/vue" || true
chmod a-x ${NODE_FOLDER}/esbuild/bin/esbuild || true
chmod a-x ${NODE_FOLDER}/typescript/bin/tsc || true
chmod a-x ${NODE_FOLDER}/vue-tsc/bin/vue-tsc.js || true
chmod g-w "${SUBMITTY_INSTALL_DIR}/site/incremental_build" || true
chmod a-x ${NODE_FOLDER}/vite/bin/vite.js || true
chmod -R u-rw ${NODE_FOLDER}/.vue-global-types || true
chmod -R u-w "${SUBMITTY_INSTALL_DIR}/site/incremental_build" || true

chmod 551 ${SUBMITTY_INSTALL_DIR}/site/public/mjs || true
set_mjs_permission ${SUBMITTY_INSTALL_DIR}/site/public/mjs || true

find ${SUBMITTY_INSTALL_DIR}/site/cache -type d -exec chmod u+w {} \; || true

PHP_VERSION=$(php -r 'print PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
set +e
systemctl is-active --quiet php${PHP_VERSION}-fpm
if [[ "$?" == "0" ]]; then
    systemctl reload php${PHP_VERSION}-fpm
fi
set -e

rm -f ${SUBMITTY_INSTALL_DIR}/site/public/index.html || true
