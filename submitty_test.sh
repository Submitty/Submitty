#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
IMAGE_NAME="submitty_test"

# build docker image
echo "Setting up Docker image '$IMAGE_NAME'..."
docker build -t "$IMAGE_NAME" "$SCRIPT_DIR"

# define the docker run command
DOCKER_RUN=(docker run --rm -u "$(id -u):$(id -g)" -e HOME=/tmp \
    -v "$SCRIPT_DIR/site:/site" \
    -v /site/vendor \
    -v /site/node_modules \
    --init \
    "$IMAGE_NAME")

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
        "${DOCKER_RUN[@]}" composer run-script static-analysis -- "${ARGS[@]}"
    else
        "${DOCKER_RUN[@]}" composer run-script static-analysis
    fi
}
run_php_cs() {
    parse_args "${@:2}"
    if $FIX; then
        "${DOCKER_RUN[@]}" vendor/bin/phpcbf "${ARGS[@]}"
    elif [ ${#ARGS[@]} -gt 0 ]; then
        "${DOCKER_RUN[@]}" composer run-script lint -- "${ARGS[@]}"
    else
        "${DOCKER_RUN[@]}" composer run-script lint
    fi
}
run_js_es() {
    parse_args "${@:2}"
    if $FIX; then
        if [ ${#ARGS[@]} -gt 0 ]; then
            "${DOCKER_RUN[@]}" npm run eslint:fix -- "${ARGS[@]}"
        else
            "${DOCKER_RUN[@]}" npm run eslint:fix
        fi
    else
        if [ ${#ARGS[@]} -gt 0 ]; then
            "${DOCKER_RUN[@]}" npm run eslint -- "${ARGS[@]}"
        else
            "${DOCKER_RUN[@]}" npm run eslint
        fi
    fi
}
run_css_style() {
    parse_args "${@:2}"
    if $FIX; then
        if [ ${#ARGS[@]} -gt 0 ]; then
            "${DOCKER_RUN[@]}" npm run css-stylelint:fix -- "${ARGS[@]}"
        else
            "${DOCKER_RUN[@]}" npm run css-stylelint:fix
        fi
    else
        if [ ${#ARGS[@]} -gt 0 ]; then
            "${DOCKER_RUN[@]}" npm run css-stylelint -- "${ARGS[@]}"
        else
            "${DOCKER_RUN[@]}" npm run css-stylelint
        fi
    fi
}
run_php_unit() {
    parse_args "${@:2}"
    "${DOCKER_RUN[@]}" php vendor/bin/phpunit "${ARGS[@]}"
}

# process input arguments
if [ -z "${1:-}" ] || [ "$1" == "help" ]; then
    echo "
          phpstan : php static analysis [option: --memory-limit 4G, --generate-baseline ...]
          phpcs   : php CodeSniffer [option: --fix]
          php-lint: phpcs & phpstan [option: --fix]
          php-unit: run php unit tests [option: --filter testFunctionName, --debug, testFile ...]
          js-lint : eslint [option: --fix]
          css-lint: css-stylelint [option: --fix]
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
        use phpstan, phpcs, php-lint, php-unit, js-lint, css-lint
        or help for detail"
fi
