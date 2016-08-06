#!/bin/bash

SOURCE="${BASH_SOURCE[0]}"
while [ -h "$SOURCE" ]; do # resolve $SOURCE until the file is no longer a symlink
  COMMON_DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"
  SOURCE="$(readlink "$SOURCE")"
  [[ ${SOURCE} != /* ]] && SOURCE="$COMMON_DIR/$SOURCE" # if $SOURCE was a relative symlink, we need to resolve it relative to the path where the symlink file was located
done
COMMON_DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"

SELENIUM_HUB_URL='http://127.0.0.1:4444'
SELENIUM_JAR=${COMMON_DIR}/selenium-server-standalone.jar
SELENIUM_DOWNLOAD_URL=http://selenium-release.storage.googleapis.com/3.0-beta1/selenium-server-standalone-3.0.0-beta1.jar
PHP_VERSION=$(php -v)

