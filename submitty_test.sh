#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
IMAGE_NAME="submitty_test"

# handle rebuild vs run
if [ "${1:-}" == "rebuild" ]; then
    echo "Rebuilding Docker image '$IMAGE_NAME' from scratch..."
    docker build --pull --no-cache -t "$IMAGE_NAME" "$SCRIPT_DIR"
    shift
else
    # build docker image
    echo "Setting up Docker image '$IMAGE_NAME'..."
    docker build -t "$IMAGE_NAME" "$SCRIPT_DIR"
fi

# runs a command in the container with a given working directory
run_in_container() {
    local workdir="$1"
    shift
    docker run --rm -t -u "$(id -u):$(id -g)" -e HOME=/tmp \
        --mount type=bind,source="$SCRIPT_DIR",target=/submitty \
        --mount type=volume,target=/submitty/site/vendor \
        --mount type=volume,target=/submitty/site/node_modules \
        -w "$workdir" \
        --init \
        "$IMAGE_NAME" "$@"
}

# parse args with --fix options
parse_args() {
    FIX=false
    ARGS=()
    for arg in "$@"; do
        if [ "$arg" == "--fix" ]; then
            FIX=true
        else
            ARGS+=("$arg")
        fi
    done
}

# functions for tests and linters
run_php_stan() {
    parse_args "${@:2}"
    if [ ${#ARGS[@]} -gt 0 ]; then
        run_in_container /submitty/site composer run-script static-analysis -- "${ARGS[@]}"
    else
        run_in_container /submitty/site composer run-script static-analysis
    fi
}
run_php_cs() {
    parse_args "${@:2}"
    if $FIX; then
        run_in_container /submitty/site vendor/bin/phpcbf "${ARGS[@]}"
    elif [ ${#ARGS[@]} -gt 0 ]; then
        run_in_container /submitty/site composer run-script lint -- "${ARGS[@]}"
    else
        run_in_container /submitty/site composer run-script lint
    fi
}
run_js_es() {
    parse_args "${@:2}"
    if $FIX; then
        if [ ${#ARGS[@]} -gt 0 ]; then
            run_in_container /submitty/site npm run eslint:fix -- "${ARGS[@]}"
        else
            run_in_container /submitty/site npm run eslint:fix
        fi
    else
        if [ ${#ARGS[@]} -gt 0 ]; then
            run_in_container /submitty/site npm run eslint -- "${ARGS[@]}"
        else
            run_in_container /submitty/site npm run eslint
        fi
    fi
}
run_css_style() {
    parse_args "${@:2}"
    if $FIX; then
        if [ ${#ARGS[@]} -gt 0 ]; then
            run_in_container /submitty/site npm run css-stylelint:fix -- "${ARGS[@]}"
        else
            run_in_container /submitty/site npm run css-stylelint:fix
        fi
    else
        if [ ${#ARGS[@]} -gt 0 ]; then
            run_in_container /submitty/site npm run css-stylelint -- "${ARGS[@]}"
        else
            run_in_container /submitty/site npm run css-stylelint
        fi
    fi
}
run_php_unit() {
    parse_args "${@:2}"
    run_in_container /submitty/site php vendor/bin/phpunit "${ARGS[@]}"
}

# run python lint

# process input arguments
if [ -z "${1:-}" ] || [ "$1" == "help" ]; then
    echo "
          rebuild   : force rebuild the docker container, including base image
          phpstan   : php static analysis [option: --memory-limit 4G, --generate-baseline ...]
          phpcs     : php CodeSniffer [option: --fix]
          php-lint  : phpcs & phpstan [option: --fix]
          php-unit  : run php unit tests [option: --filter testFunctionName, --debug, testFile ...]
          js-lint   : eslint [option: --fix]
          css-lint  : css-stylelint [option: --fix]
          "
elif [ "$1" == "phpstan" ]; then
    run_php_stan "$@"
elif [ "$1" == "phpcs" ]; then
    run_php_cs "$@"
elif [ "$1" == "php-lint" ]; then
    run_php_cs "$@"
    run_php_stan "$@"
elif [ "$1" == "php-unit" ]; then
    run_php_unit "$@"
elif [ "$1" == "js-lint" ]; then
    run_js_es "$@"
elif [ "$1" == "css-lint" ]; then
    run_css_style "$@"
else
    echo "Unknown test type: $1
        use rebuild, phpstan, phpcs, php-lint, php-unit, js-lint, css-lint
        or help for detail"
fi
