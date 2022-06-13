#!/bin/bash
if [ "$#" -eq 0 ]; then
  echo "Pass at least one directory or file" >&2
  exit 1
fi
mapfile -t csv < <( cloc --csv --quiet "$@" )
if [ "${#csv[@]}" -eq 0 ]; then
  echo "File does not exist" >&2
  exit 1
fi
result="${csv[-1]}"
COMMENT_INDEX=3
IFS=','
read -a result <<< "${result}"
echo "${result[COMMENT_INDEX]}"
