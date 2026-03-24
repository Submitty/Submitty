#!/usr/bin/env bash

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

echo -e "Copy the submission website"

THIS_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null && pwd )"
source ${THIS_DIR}/../bin/versions.sh

CONF_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"/../../../../config
SUBMITTY_REPOSITORY=$(jq -r '.submitty_repository' ${CONF_DIR}/submitty.json)
SUBMITTY_INSTALL_DIR=$(jq -r '.submitty_install_dir' ${CONF_DIR}/submitty.json)
SUBMITTY_DATA_DIR=$(jq -r '.submitty_data_dir' ${SUBMITTY_INSTALL_DIR}/config/submitty.json)
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

if [ -d "${SUBMITTY_INSTALL_DIR}/site/ts" ]; then
	rm -r "${SUBMITTY_INSTALL_DIR}/site/ts"
fi

if [ -d "${SUBMITTY_INSTALL_DIR}/site/vue" ]; then
	rm -r "${SUBMITTY_INSTALL_DIR}/site/vue"
fi

result=$(rsync -rtz -i --exclude-from ${SUBMITTY_REPOSITORY}/site/.rsyncignore ${SUBMITTY_REPOSITORY}/site ${SUBMITTY_INSTALL_DIR})
if [ ${VAGRANT} == 1 ]; then
	rsync -rtz -i ${SUBMITTY_REPOSITORY}/site/tests ${SUBMITTY_REPOSITORY}/site/phpunit.xml ${SUBMITTY_INSTALL_DIR}/site > /dev/null
	chown -R ${PHP_USER}:${PHP_GROUP} ${SUBMITTY_INSTALL_DIR}/site/tests
	chmod -R 740 ${SUBMITTY_INSTALL_DIR}/site/tests
	chown ${PHP_USER}:${PHP_GROUP} ${SUBMITTY_INSTALL_DIR}/site/phpunit.xml
	chmod 740 ${SUBMITTY_INSTALL_DIR}/site/phpunit.xml
fi

if [ ! -d ${SUBMITTY_INSTALL_DIR}/site/vendor ]; then
	mkdir ${SUBMITTY_INSTALL_DIR}/site/vendor
	chown -R ${PHP_USER}:${PHP_USER} ${SUBMITTY_INSTALL_DIR}/site/vendor
	result=$(echo -e "${result}\n>f+++++++++ site/composer.json")
fi

if [ ! -d ${SUBMITTY_INSTALL_DIR}/site/node_modules ]; then
	result=$(echo -e "${result}\n>f+++++++++ site/package.json")
fi

readarray -t result_array <<< "${result}"

if [ -d "${SUBMITTY_INSTALL_DIR}/site/cache/twig" ]; then
	rm -rf "${SUBMITTY_INSTALL_DIR}/site/cache/twig"
fi

mkdir -p ${SUBMITTY_INSTALL_DIR}/site/cache/twig

if [ -d "${SUBMITTY_INSTALL_DIR}/site/cache/routes" ]; then
	rm -rf "${SUBMITTY_INSTALL_DIR}/site/cache/routes"
fi

mkdir -p ${SUBMITTY_INSTALL_DIR}/site/cache/routes

if [ -d "${SUBMITTY_INSTALL_DIR}/site/cache/doctrine" ]; then
	rm -rf "${SUBMITTY_INSTALL_DIR}/site/cache/doctrine"
fi

mkdir -p ${SUBMITTY_INSTALL_DIR}/site/cache/doctrine

if [ -d "${SUBMITTY_INSTALL_DIR}/site/cache/access_control" ]; then
	rm -rf "${SUBMITTY_INSTALL_DIR}/site/cache/access_control"
fi

mkdir -p ${SUBMITTY_INSTALL_DIR}/site/cache/access_control

if [ -d "${SUBMITTY_INSTALL_DIR}/site/cache/doctrine-proxy" ]; then
	rm -rf "${SUBMITTY_INSTALL_DIR}/site/cache/doctrine-proxy"
fi

mkdir -p ${SUBMITTY_INSTALL_DIR}/site/cache/doctrine-proxy

if [ -d "${SUBMITTY_INSTALL_DIR}/site/cache/lang" ]; then
	rm -rf "${SUBMITTY_INSTALL_DIR}/site/cache/lang"
fi

mkdir -p ${SUBMITTY_INSTALL_DIR}/site/cache/lang

if [ -d "${SUBMITTY_INSTALL_DIR}/site/public/mjs" ]; then
	rm -r "${SUBMITTY_INSTALL_DIR}/site/public/mjs"
fi

mkdir -p ${SUBMITTY_INSTALL_DIR}/site/public/mjs

# Update ownership to PHP_USER for affected files and folders
chown ${PHP_USER}:${PHP_GROUP} ${SUBMITTY_INSTALL_DIR}/site
for entry in "${result_array[@]}"; do
	chown ${PHP_USER}:${PHP_GROUP} "${SUBMITTY_INSTALL_DIR}/${entry:12}"
done

find ${SUBMITTY_INSTALL_DIR}/site/cgi-bin -exec chown ${CGI_USER}:${CGI_GROUP} {} \;

chown ${PHP_USER}:${PHP_GROUP} ${SUBMITTY_INSTALL_DIR}/site/public/mjs

if [ -d "${SUBMITTY_INSTALL_DIR}/site/vendor/composer" ]; then
	chmod 640 ${SUBMITTY_INSTALL_DIR}/site/composer.lock
	chmod -R 740 ${SUBMITTY_INSTALL_DIR}/site/vendor
fi

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
