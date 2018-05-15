#!/usr/bin/env bash

# This runs selenium on a local machine without some of the other stuff required for travis (such as installing
# necessary packages. Run this script after you have done:
# 1) Install Apache, PHP, and Postgres
# 2) Have the server being served out of localhost/public
# This will then download the selenium jar (if it doesn't exist in this directory), then start it up


SOURCE="${BASH_SOURCE[0]}"
# resolve $SOURCE until the file is no longer a symlink
while [ -h "$SOURCE" ]; do
  DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"
  SOURCE="$(readlink "$SOURCE")"
  # if $SOURCE was a relative symlink, we need to resolve
  # it relative to the path where the symlink file was located
  [[ ${SOURCE} != /* ]] && SOURCE="$DIR/$SOURCE"
done
DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"

source ${DIR}/common/common_env.sh

if [ ! -f "$SELENIUM_JAR" ]; then
    echo "Downloading Selenium"
    sudo mkdir -p $(dirname "${SELENIUM_JAR}")
    sudo wget -O "${SELENIUM_JAR}" "${SELENIUM_DOWNLOAD_URL}"
    echo "Downloaded Selenium"
fi

echo "Starting selenium..."
java -jar "${SELENIUM_JAR}"
# Goto http://localhost:4444/selenium-server/driver/?cmd=shutDownSeleniumServer to kill it
