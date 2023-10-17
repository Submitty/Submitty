#!/bin/bash

install_composer() {
    if ! command -v composer &> /dev/null; then
        echo "Composer not found. Installing Composer..."
        EXPECTED_SIGNATURE=$(wget https://composer.github.io/installer.sig -O - -q)
        php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
        ACTUAL_SIGNATURE=$(php -r "echo hash_file('sha384', 'composer-setup.php');")

        if [ "$EXPECTED_SIGNATURE" != "$ACTUAL_SIGNATURE" ]; then
            >&2 echo 'ERROR: Invalid installer signature'
            rm composer-setup.php
            exit 1
        fi

        php composer-setup.php --quiet
        RESULT=$?
        rm composer-setup.php
        exit $RESULT
    fi
}

pushd /usr/local/submitty/GIT_CHECKOUT/Submitty/site || {
    echo "Failed to change to the Submitty/site directory.
    Please check if you have /usr/local/submitty/GIT_CHECKOUT/Submitty/site and valid permission"
    exit 1
} > /dev/null

install_composer


run_php_stan() {
    COMPOSER_ALLOW_SUPERUSER=1 composer run-script static-analysis "${@:2}" 2>/dev/null
}

run_php_cs() {
    COMPOSER_ALLOW_SUPERUSER=1 composer run-script lint 2>/dev/null
}


submitty_test() {
    if [ "$1" == "phpstan" ]; then
        run_php_stan "$@"
    elif [ "$1" == "phpcs" ]; then
        run_php_cs
    elif [ "$1" == "php-lint" ]; then
        run_php_cs
        run_php_stan "$@"
    elif [ "$1" == "help" ]; then
        echo "phpstan: php static analysis [option: --memory-limit 4G, --generate-baseline ...]
              phpcs  : php CodeSniffer
              php-lint: phpcs & phpstan"
    else
        echo "Unknown test type: $1
            use phpstan, phpcs, php-lint
            or help for detail"
    fi
}

submitty_test "$@"

popd || {
    echo "Failed to return to the previous directory. Check if you have valid permission."
    exit 1
} > /dev/null
