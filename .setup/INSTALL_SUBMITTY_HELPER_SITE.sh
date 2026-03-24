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

set -e
THIS_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null && pwd )"
SUBMITTY_CONFIG_DIR="/usr/local/submitty/config"

SUBMITTY_REPOSITORY=$(jq -r '.submitty_repository' ${SUBMITTY_CONFIG_DIR}/submitty.json)
source "${SUBMITTY_REPOSITORY}/.setup/install_submitty/get_globals.sh" "config=${SUBMITTY_CONFIG_DIR:?}"

SUBMITTY_INSTALL_DIR=$(jq -r '.submitty_install_dir' ${SUBMITTY_CONFIG_DIR}/submitty.json)
NODE_FOLDER=${SUBMITTY_INSTALL_DIR}/site/node_modules


bash "${THIS_DIR}/install_submitty/copy_site.sh" browscap "config=${SUBMITTY_CONFIG_DIR:?}"

if [[ "$1" == "--full" ]]; then
    bash "${THIS_DIR}/install_submitty/install_dependencies.sh" browscap "config=${SUBMITTY_CONFIG_DIR:?}"
fi

echo "Running esbuild"
chmod a+x ${NODE_FOLDER}/esbuild/bin/esbuild
chmod a+x ${NODE_FOLDER}/typescript/bin/tsc
chmod a+x ${NODE_FOLDER}/vue-tsc/bin/vue-tsc.js
chmod -R u+rw ${NODE_FOLDER}/.vue-global-types
chmod a+x ${NODE_FOLDER}/vite/bin/vite.js
chmod g+w "${SUBMITTY_INSTALL_DIR}/site/incremental_build"
chmod -R u+w "${SUBMITTY_INSTALL_DIR}/site/incremental_build"
chmod +w "${SUBMITTY_INSTALL_DIR}/site/vue"
su - ${PHP_USER} -c "cd ${SUBMITTY_INSTALL_DIR}/site && npm run build"
chmod -w "${SUBMITTY_INSTALL_DIR}/site/vue"
chmod a-x ${NODE_FOLDER}/esbuild/bin/esbuild
chmod a-x ${NODE_FOLDER}/typescript/bin/tsc
chmod a-x ${NODE_FOLDER}/vue-tsc/bin/vue-tsc.js
chmod g-w "${SUBMITTY_INSTALL_DIR}/site/incremental_build"
chmod a-x ${NODE_FOLDER}/vite/bin/vite.js
chmod -R u-rw ${NODE_FOLDER}/.vue-global-types
chmod -R u-w "${SUBMITTY_INSTALL_DIR}/site/incremental_build"

chmod 551 ${SUBMITTY_INSTALL_DIR}/site/public/mjs
set_mjs_permission ${SUBMITTY_INSTALL_DIR}/site/public/mjs

rm -f ${SUBMITTY_INSTALL_DIR}/site/public/index.html
