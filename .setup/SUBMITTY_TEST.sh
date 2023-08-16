#!/bin/bash


current_dir=$(pwd)

cd /usr/local/submitty/GIT_CHECKOUT/Submitty/site

submitty_test() {
    if [ "$1" == "php_stan" ]; then
        php vendor/bin/phpstan analyze app public/index.php socket/index.php "${@:2}"
    elif [ "$1" == "php_lint" ]; then
        php vendor/bin/phpcs --extensions=php ./app
elif [ "$1" == "php_lint_stan" ] || [ "$1" == "php_stan_lint" ]; then
        php vendor/bin/phpcs --extensions=php ./app
        php vendor/bin/phpstan analyze app public/index.php socket/index.php "${@:2}"
    elif [ "$1" == "js_lint" ]; then
        npm run eslint
    elif [ "$1" == "css_lint" ]; then
        npm run css-stylelint
    elif [ "$1" == "all" ]; then
        php vendor/bin/phpstan analyze app public/index.php socket/index.php
        php vendor/bin/phpcs --extensions=php ./app
        npm run eslint
        npm run css-stylelint
    elif [ "$1" == "help" ]; then
        echo "php_stan: php static analysis
                        handy additional options examples
                        php_stan --memory-limit=4G
                        php_stan --generate-baseline
              php_lint: php linting
              js_lint : javascript linting
              css_lint: css linting
              php_lint_stan: php linting and static analysis
              all: php_stan, php_lint, js_lint, css_lint"
    else
        echo "Unknown test type: $1
            use php_stan, php_lint, js_lint, css_lint
            or help for detail"
    fi
}

submitty_test "$@"
cd "$current_dir"
