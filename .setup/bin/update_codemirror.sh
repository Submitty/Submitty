#!/usr/bin/env bash

# Updates the CodeMirror (https://codemirror.net) dependency in the root site/ directory.

set -e

echo "Updating CodeMirror..."
if [ ! -d /tmp/codemirror ]; then
	wget https://codemirror.net/codemirror.zip -O /tmp/codemirror.zip
	unzip /tmp/codemirror -d /tmp/codemirror
fi
VERSION=$(ls -1A /tmp/codemirror | grep -E -o "([0-9]+\.[0-9]+\.[0-9]+)")

CODEMIRROR=/tmp/codemirror/codemirror-${VERSION}
THIS_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null && pwd )"
DEST=${THIS_DIR}/../../site/public/

echo "Found version ${VERSION}..."
cp -f ${CODEMIRROR}/lib/codemirror.js ${DEST}/js/iframe
echo "// VERSION: ${VERSION}" | cat - ${DEST}/js/iframe/codemirror.js > /tmp/out && mv /tmp/out ${DEST}/js/iframe/codemirror.js
modes=( clike python shell )
for i in "${modes[@]}"; do
	cp -f ${CODEMIRROR}/mode/${i}/${i}.js ${DEST}/js/iframe
done

cp -f ${CODEMIRROR}/lib/codemirror.css ${DEST}/css/iframe
themes=( eclipse monokai )
for i in "${themes[@]}"; do
	cp -f ${CODEMIRROR}/theme/${i}.css ${DEST}/css/iframe
done

rm -rf /tmp/codemirror*
