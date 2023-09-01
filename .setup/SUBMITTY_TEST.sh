#!/bin/bash

pushd /usr/local/submitty/GIT_CHECKOUT/Submitty/site || {
    echo "Failed to change to the Submitty/site directory.
    Please check if you have /usr/local/submitty/GIT_CHECKOUT/Submitty/site and valid permission"
    exit 1
} > /dev/null

run_php_stan() {
    php vendor/bin/phpstan analyze app public/index.php socket/index.php "${@:2}"
}

run_php_cs() {
    php vendor/bin/phpcs --extensions=php ./app
}

submitty_test() {
    if [ "$1" == "php-stan" ]; then
        run_php_stan "$@"
    elif [ "$1" == "php-cs" ]; then
        run_php_cs
    elif [ "$1" == "php-lint" ]; then
        run_php_cs
        run_php_stan "$@"
    elif [ "$1" == "help" ]; then
        echo "php-stan: php static analysis [option: --memory-limit=4G, --generate-baseline ...]
              php-cs  : php CodeSniffer
              php-lint: php-cs & php-stan"
    else
        echo "Unknown test type: $1
            use php-stan, php-cs, php-lint
            or help for detail"
    fi
}

submitty_test "$@"

popd || {
    echo "Failed to return to the previous directory. Check if you have valid permission."
    exit 1
} > /dev/null
