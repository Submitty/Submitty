#!/usr/bin/env bash
set -ev

for cli_arg in "$@"
do
    if [[ $cli_arg =~ ^config=.* ]]; then
        SUBMITTY_CONFIG_DIR="$(readlink -f "$(echo "$cli_arg" | cut -f2 -d=)")"
    fi
done

if [ -z "${SUBMITTY_CONFIG_DIR}" ]; then
    echo "ERROR: This script requires a config dir argument"
    echo "Usage: ${BASH_SOURCE[0]} config=<config dir>"
    exit 1
fi

SUBMITTY_REPOSITORY=$(jq -r '.submitty_repository' "${SUBMITTY_CONFIG_DIR:?}/submitty.json")
# shellcheck disable=SC1091
source "${SUBMITTY_REPOSITORY:?}/.setup/install_submitty/get_globals.sh" "config=${SUBMITTY_CONFIG_DIR:?}"
########################################################################################################################
########################################################################################################################
# BUILD JUNIT TEST RUNNER (.java file) if Java is installed on the machine

if [ -x "$(command -v javac)" ] &&
   [ -d "${SUBMITTY_INSTALL_DIR}/java_tools/JUnit" ]; then
    echo -e "Build the junit test runner"

    # copy the file from the repo
    rsync -rtz "${SUBMITTY_REPOSITORY}/junit_test_runner/TestRunner.java" "${SUBMITTY_INSTALL_DIR}/java_tools/JUnit/TestRunner.java"

    pushd "${SUBMITTY_INSTALL_DIR}/java_tools/JUnit" > /dev/null
    # root will be owner & group of the source file
    chown  root:root  TestRunner.java
    # everyone can read this file
    chmod  444 TestRunner.java

    # compile the executable using the javac we use in the execute.cpp safelist
    /usr/bin/javac -cp ./junit-4.12.jar TestRunner.java

    # everyone can read the compiled file
    chown root:root TestRunner.class
    chmod 444 TestRunner.class

    popd > /dev/null


    # fix all java_tools permissions
    chown -R "root:${COURSE_BUILDERS_GROUP}" "${SUBMITTY_INSTALL_DIR}/java_tools"
    chmod -R 755                             "${SUBMITTY_INSTALL_DIR}/java_tools"
else
    echo -e "Skipping build of the junit test runner"
fi
