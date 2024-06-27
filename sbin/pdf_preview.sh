#!/bin/bash

SRC=$1
DST=$2

TMPDIR=$(mktemp -d)

echo $TMPDIR

cd $TMPDIR
echo "file is $SRC"
cp "$SRC" file.docx
libreoffice --headless --convert-to pdf file.docx
ls -l

mv file.pdf "$DST"
cd
rmdir "$TMPDIR"
