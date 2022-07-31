#!/bin/bash

# As of August 2022, ShellCheck does not offer an option for a list of ignored files
# This script serves as a wrapper around ShellCheck to only run ShellCheck on
# shell scripts not listed in .shellcheckignore

# shellcheck disable=SC2207
scfiles=($(find . -type f -name "*.sh"))

# Requires bash 4+
shopt -s globstar

while read -r p; do
    # shellcheck disable=SC2206
    ignoredfiles=($p)
    for i in "${scfiles[@]}"; do
        # shellcheck disable=SC2076
        if [[ " ${ignoredfiles[*]} " =~ " ${i:2} " ]]; then
            scfiles=("${scfiles[@]/$i}")
        fi
    done
done < .shellcheckignore

# Hacky way to get rid of the empty spaces
# shellcheck disable=SC2206
scfiles=(${scfiles[*]})

shellcheck "${scfiles[@]}"
