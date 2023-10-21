#!/bin/bash

pushd /usr/local/submitty/GIT_CHECKOUT/Submitty/site  > /dev/null || {
    echo "Failed to change to /usr/local/submitty/GIT_CHECKOUT/Submitty/site."
    exit 1
}

COMPOSER_ALLOW_SUPERUSER=1 composer install

run_php_stan() {
    COMPOSER_ALLOW_SUPERUSER=1 composer run-script static-analysis "${@:2}" 2>/dev/null
}

run_php_cs() {
    COMPOSER_ALLOW_SUPERUSER=1 composer run-script lint 2>/dev/null
}

run_js_es() {
    npm run eslint
}

run_css_style() {
    npm run css-stylelint
}
if [ "$1" == "phpstan" ]; then
    run_php_stan "$@"
elif [ "$1" == "phpcs" ]; then
    run_php_cs
elif [ "$1" == "php-lint" ]; then
    run_php_cs
    run_php_stan "$@"
elif [ "$1" == "js-lint" ]; then
    run_js_es
elif [ "$1" == "css-lint" ]; then
    run_css_style
elif [ "$1" == "help" ]; then
    echo "phpstan: php static analysis [option: --memory-limit 4G, --generate-baseline ...]
          phpcs  : php CodeSniffer
          php-lint: phpcs & phpstan"
else
    echo "Unknown test type: $1
        use phpstan, phpcs, php-lint
        or help for detail"
fi

popd > /dev/null || {
    echo "Failed to return to the previous directory."
    exit 1
}
