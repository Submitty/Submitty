#!/bin/bash

pushd /usr/local/submitty/GIT_CHECKOUT/Submitty/site  > /dev/null || {
    echo "Failed to change to /usr/local/submitty/GIT_CHECKOUT/Submitty/site."
    exit 1
}

run_php_stan() {
    COMPOSER_ALLOW_SUPERUSER=1 composer install
    COMPOSER_ALLOW_SUPERUSER=1 composer run-script static-analysis "${@:2}" 2>/dev/null
}

run_php_cs() {
    COMPOSER_ALLOW_SUPERUSER=1 composer install
    COMPOSER_ALLOW_SUPERUSER=1 composer run-script lint 2>/dev/null
}


if [ -z "$1" ] || [ "$1" == "help" ]; then
    echo "
          phpstan : php static analysis [option: --memory-limit 4G, --generate-baseline ...]
          phpcs   : php CodeSniffer
          php-lint: phpcs & phpstan
          "
elif [ "$1" == "phpstan" ]; then
    run_php_stan "$@"
elif [ "$1" == "phpcs" ]; then
    run_php_cs
elif [ "$1" == "php-lint" ]; then
    run_php_cs
    run_php_stan "$@"
else
    echo "
        Unknown test type: $1
        use phpstan, phpcs, php-lint
        or help for detail
        "
fi

popd > /dev/null || {
    echo "Failed to return to the previous directory."
    exit 1
}
