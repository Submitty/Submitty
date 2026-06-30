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

run_npm_scripts_in_tmp_dir() {

    local script="$1"
    local option="$2"

    echo "Copying site/ to /tmp/submitty_test to bypass permissions..."

    # copy site/ to a folder in /tmp
    TMP_FOLDER="/tmp/submitty_test"

    mkdir -p "$TMP_FOLDER"

    if [ ! -d "$TMP_FOLDER" ]; then
        echo "ERROR: The /tmp/submitty_test folder could not be generated."
        exit 1
    fi

    rsync -a --delete --exclude='node_modules' . "$TMP_FOLDER/"

    # change to tmp folder
    pushd "$TMP_FOLDER" > /dev/null || {
        echo "Failed to change to /tmp/submitty_test"
        exit 1
    }

    # set up npm
    npm install
    chmod -R +x node_modules/.bin/

    if [ "$option" = "--fix" ]; then
        npm run "${script}:fix"
    else
        npm run "$script"
    fi

    local result=$?

    if [ "$option" = "--fix" ]; then
        echo "Syncing fixed files back to the shared mount..."
        rsync -a --exclude='node_modules' . "$OLDPWD/"
    fi

    # exit and clean up the temporary directory
    popd > /dev/null || {
        echo "ERROR: failed to exit /tmp/submitty_test"
        exit 1
    }

    return $result

}

run_js_es() {
    run_npm_scripts_in_tmp_dir "eslint" "$2"
}

run_css_style() {
    run_npm_scripts_in_tmp_dir "css-stylelint" "$2"
}

run_php_unit() {
    COMPOSER_ALLOW_SUPERUSER=1 composer install
    sudo -u submitty_php php vendor/bin/phpunit "${@:2}"
}

if [ -z "$1" ] || [ "$1" == "help" ]; then
    echo "
          phpstan : php static analysis [option: --memory-limit 4G, --generate-baseline ...]
          phpcs   : php CodeSniffer
          php-lint: phpcs & phpstan
          php-unit: run php unit tests [option: --filter testFunctionName, --debug, testFile ...]
          js-lint : eslint
          css-lint: css-stylelint
          "
elif [ "$1" == "phpstan" ]; then
    run_php_stan "$@"
elif [ "$1" == "phpcs" ]; then
    run_php_cs
elif [ "$1" == "php-lint" ]; then
    run_php_cs
    run_php_stan "$@"
elif [ "$1" == "php-unit" ]; then
    run_php_unit "$@"
elif [ "$1" == "js-lint" ]; then
    run_js_es "$@"
elif [ "$1" == "css-lint" ]; then
    run_css_style "$@"
else
    echo "Unknown test type: $1
        use phpstan, phpcs, php-lint, php-unit, js-lint, css-lint
        or help for detail"
fi

popd > /dev/null || {
    echo "Failed to return to the previous directory."
    exit 1
}
