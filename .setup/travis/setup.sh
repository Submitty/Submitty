#!/bin/bash

if [[ "$RUN_E2E" = "true" ]]; then
    apt-get update > /dev/null
    apt-get install -yqq --force-yes apache2 libapache2-mod-php5 php5-curl php5-intl php5-pgsql


    sed -i -e "s,/var/www,$(pwd),g" /etc/apache2/sites-available/default
    # cat /etc/apache2/sites-available/default
    /etc/init.d/apache2 restart

    sh -e /etc/init.d/xvfb start
    export DISPLAY=:99.0

    if [ ! -f "$SELENIUM_JAR" ]; then
        echo "Downloading Selenium"
        mkdir -p $(dirname "$SELENIUM_JAR")
        wget -nv -O "$SELENIUM_JAR" "$SELENIUM_DOWNLOAD_URL"
        echo "Downloaded Selenium"
    fi

    echo "Installing Firefox"
    apt-get install firefox -yqq --no-install-recommends
fi

echo "Setting up config files"
cp "$TRAVIS_BUILD_DIR/tests/test.php" "$TRAVIS_BUILD_DIR/TAGradingServer/toolbox/configs/master.php"
touch "$TRAVIS_BUILD_DIR/TAGradingServer/toolbox/configs/test_course.php"

echo "Setting up auto-grader test suite"
apt-get install -yqq --force-yes python automake cmake make clang gcc g++ g++-multilib
mkdir -p /usr/local/hss
mkdir -p /var/local/hss
mkdir -p /usr/local/hss/src
cp -r tests /usr/local/hss/test_suite
cp -r sample_files /usr/local/hss/sample_files
cp -r grading/ /usr/local/hss/src/
ls /usr/local/hss
ls /usr/local/hss/src
ls /usr/local/hss/src/grading

sed -i -e "s|__INSTALL__FILLIN__HSS_INSTALL_DIR__|/usr/local/hss|g" /usr/local/hss/test_suite/integrationTests/scripts/lib.py
sed -i -e "s|__INSTALL__FILLIN__HSS_INSTALL_DIR__|/usr/local/hss|g" /usr/local/hss/test_suite/integrationTests/scripts/run.py
sed -i -e "s|__INSTALL__FILLIN__HSS_INSTALL_DIR__|/usr/local/hss|g" /usr/local/hss/src/grading/system_call_check.cpp
sed -i -e "s|__INSTALL__FILLIN__HSS_INSTALL_DIR__|/usr/local/hss|g" /usr/local/hss/src/grading/Sample_CMakeLists.txt
sed -i -e "s|__INSTALL__FILLIN__HSS_DATA_DIR__|/var/local/hss|g" /usr/local/hss/src/grading/Sample_CMakeLists.txt
sed -i -e "s|__INSTALL__FILLIN__HSS_INSTALL_DIR__|/usr/local/hss|g" /usr/local/hss/src/grading/CMakeLists.txt
sed -i -e "s|__INSTALL__FILLIN__HSS_DATA_DIR__|/var/local/hss|g" /usr/local/hss/src/grading/CMakeLists.txt

# building the autograding library
mkdir -p /usr/local/hss/src/grading/lib
pushd /usr/local/hss/src/grading/lib
cmake ..
make
popd

chmod -R 777 /usr/local/hss