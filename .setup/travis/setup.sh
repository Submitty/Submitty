#!/bin/bash

if [[ "$RUN_E2E" = "true" ]]; then
    sudo iptables -t nat -A OUTPUT -p all -d 192.168.56.104 -j DNAT --to-destination 127.0.0.1
    sudo chmod -R 755 /home/travis/build
    sudo usermod -a -G travis www-data
    sudo apt-get update > /dev/null
    sudo apt-get install -yqq --force-yes apache2 libapache2-mod-php5 php5-curl php5-intl php5-pgsql php5-curl

    sudo cp ${BEFORE_SCRIPT_DIR}/travis/000-default.conf /etc/apache2/sites-available/000-default.conf
    sudo /etc/init.d/apache2 restart

    if [ ! -f "$SELENIUM_JAR" ]; then
        echo "Downloading Selenium"
        sudo mkdir -p $(dirname "$SELENIUM_JAR")
        sudo wget -nv -O "$SELENIUM_JAR" "$SELENIUM_DOWNLOAD_URL"
        echo "Downloaded Selenium"
    fi
fi

echo "Setting up config files"
cp ${TRAVIS_BUILD_DIR}/tests/test.php ${TRAVIS_BUILD_DIR}/TAGradingServer/toolbox/configs/master.php
touch ${TRAVIS_BUILD_DIR}/TAGradingServer/toolbox/configs/test_course.php

sudo bash ${BEFORE_SCRIPT_DIR}/travis/autograder.sh