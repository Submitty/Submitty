#!/bin/bash

sudo apt-get update > /dev/null
sudo apt-get install -y --force-yes apache2 libapache2-mod-php5 php5-curl php5-mysql php5-intl php5-pgsql > /dev/null

sudo sed -i -e "s,/var/www,$(pwd),g" /etc/apache2/sites-available/default
# cat /etc/apache2/sites-available/default
sudo /etc/init.d/apache2 restart

sh -e /etc/init.d/xvfb start
export DISPLAY=:99.0

if [ ! -f "$SELENIUM_JAR" ]; then
    echo "Downloading Selenium"
    sudo mkdir -p $(dirname "$SELENIUM_JAR")
    sudo wget -nv -O "$SELENIUM_JAR" "$SELENIUM_DOWNLOAD_URL"
    echo "Downloaded Selenium"
fi

echo "Installing Firefox"
sudo apt-get install firefox -y --no-install-recommends > /dev/null

echo "Setting up config files"
rm "$TRAVIS_BUILD_DIR/TAGradingServer/toolbox/configs/master.php"
cp "$TRAVIS_BUILD_DIR/tests/test.php" "$TRAVIS_BUILD_DIR/TAGradingServer/toolbox/configs/master.php"
touch "$TRAVIS_BUILD_DIR/TAGradingServer/toolbox/configs/test_course.php"