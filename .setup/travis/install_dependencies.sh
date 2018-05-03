#!/usr/bin/env bash

sudo add-apt-repository -y ppa:ondrej/php
sudo apt-get -qq update

PHP = ${TRAVIS_PHP_VERSION:0:3}
sudo apt-get -y install libapache2-mod-php${PHP} php${PHP}-pgsql php${PHP}-curl
a2enmod php${PHP}
