#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# SUBMITTY_ROOT is set based on if this is being used inside the vm or locally
if [ -d "/usr/local/submitty/GIT_CHECKOUT/Submitty" ]; then
    SUBMITTY_ROOT="/usr/local/submitty/GIT_CHECKOUT/Submitty"
else
    SUBMITTY_ROOT="$SCRIPT_DIR/.."
fi

IMAGE_NAME="submitty_test"
HELP_MESSAGE="
    Usage:
    help      : see usage details
    phpstan   : php static analysis [option: --memory-limit <#>G, --generate-baseline ...]
    phpcs     : php CodeSniffer [option: --fix]
    php-lint  : phpcs & phpstan (with default options only)
    php-unit  : run php unit tests [option: --filter testFunctionName, --debug, testFile ...]
    js-lint   : eslint [option: --fix]
    css-lint  : css-stylelint [option: --fix]
    py-flake8 : run flake8 [option: specific_file.py]
    py-pylint : run pylint [option: specific_file.py]
    py-lint   : py-flake8 & py-pylint [option: specific_file.py]
    py-unit   : run all python unit tests except migration
    py-unit-utils      : run the 'utils' python unit tests [option: module, class, function ...]
    py-unit-migration  : run the 'migration' python unit tests [option: module, class, function ...]
    py-unit-autograder : run the 'autograder' python unit tests [option: module, class, function ...]
    py-unit-daemon     : run the 'daemon' python unit tests [option: module, class, function ...]
"

case "${1:-}" in
    help|--help|"")
        echo "$HELP_MESSAGE"
        exit 0
        ;;
esac

# build docker image using SUBMITTY_ROOT
echo "Setting up Docker image '$IMAGE_NAME'..."
docker build -t "$IMAGE_NAME" -f "$SCRIPT_DIR/Dockerfile" "$SUBMITTY_ROOT"

# runs a command in the container with a given working directory
run_in_container() {
    local workdir="$1"
    shift
    # check if the environment supports -t (in this case, just used for color output)
    local terminal_flag=""
    [ -t 0 ] && terminal_flag="-t"
    docker run --rm $terminal_flag -u "$(id -u):$(id -g)" -e HOME=/tmp \
        --cap-drop=DAC_OVERRIDE \
        --mount type=bind,source="$SUBMITTY_ROOT",target=/home/submitty \
        --mount type=volume,target=/home/submitty/site/vendor \
        --mount type=volume,target=/home/submitty/site/node_modules \
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
        run_in_container /home/submitty/site composer run-script static-analysis -- "${ARGS[@]}"
    else
        run_in_container /home/submitty/site composer run-script static-analysis -- --memory-limit 4G
    fi
}

run_php_cs() {
    parse_args "${@:2}"
    script=lint
    $FIX && script=lint:fix

    if [ ${#ARGS[@]} -gt 0 ]; then
        run_in_container /home/submitty/site composer run-script "$script" -- "${ARGS[@]}"
    else
        run_in_container /home/submitty/site composer run-script "$script"
    fi
}

run_js_es() {
    parse_args "${@:2}"
    script=eslint
    $FIX && script=eslint:fix

    if [ ${#ARGS[@]} -gt 0 ]; then
        run_in_container /home/submitty/site npm run "$script" -- "${ARGS[@]}"
    else
        run_in_container /home/submitty/site npm run "$script"
    fi
}

run_css_style() {
    parse_args "${@:2}"
    script=css-stylelint
    $FIX && script=css-stylelint:fix

    if [ ${#ARGS[@]} -gt 0 ]; then
        run_in_container /home/submitty/site npm run "$script" -- "${ARGS[@]}"
    else
        run_in_container /home/submitty/site npm run "$script"
    fi
}

run_php_unit() {
    parse_args "${@:2}"
    run_in_container /home/submitty/site php vendor/bin/phpunit "${ARGS[@]}"
}

run_py_flake8() {
    parse_args "${@:2}"
    if [ ${#ARGS[@]} -gt 0 ]; then
        run_in_container /home/submitty python3 -m flake8 "${ARGS[@]}"
    else
        run_in_container /home/submitty python3 -m flake8
    fi
}

run_py_pylint() {
    parse_args "${@:2}"
    if [ ${#ARGS[@]} -gt 0 ]; then
        run_in_container /home/submitty python3 -m pylint "${ARGS[@]}"
    else
        run_in_container /home/submitty python3 -m pylint --recursive=y .
    fi
}

run_py_unit_utils() {
    parse_args "${@:2}"
    run_in_container /home/submitty/python_submitty_utils python3 -m unittest discover "${ARGS[@]}"
}

run_py_unit_migration() {
    parse_args "${@:2}"
    run_in_container /home/submitty/migration python3 -m unittest discover "${ARGS[@]}"
}

run_py_unit_autograder() {
    parse_args "${@:2}"
    run_in_container /home/submitty/autograder python3 -m unittest discover "${ARGS[@]}"
}

run_py_unit_daemon() {
    parse_args "${@:2}"
    run_in_container /home/submitty/sbin/submitty_daemon_jobs python3 -m unittest discover tests -t . "${ARGS[@]}"
}

# process input arguments
case "${1:-}" in
    phpstan)
        run_php_stan "$@"
        ;;
    phpcs)
        run_php_cs "$@"
        ;;
    php-lint)
        run_php_cs
        run_php_stan
        ;;
    php-unit)
        run_php_unit "$@"
        ;;
    js-lint)
        run_js_es "$@"
        ;;
    css-lint)
        run_css_style "$@"
        ;;
    py-flake8)
        run_py_flake8 "$@"
        ;;
    py-pylint)
        run_py_pylint "$@"
        ;;
    py-lint)
        echo "Running pylint..."
        run_py_pylint "$@"
        echo "Running flake8..."
        run_py_flake8 "$@"
        ;;
    py-unit-utils)
        run_py_unit_utils "$@"
        ;;
    py-unit-migration)
        run_py_unit_migration "$@"
        ;;
    py-unit-autograder)
        run_py_unit_autograder "$@"
        ;;
    py-unit-daemon)
        run_py_unit_daemon "$@"
        ;;
    py-unit)
        echo "Running unit test: 'utils'..."
        run_py_unit_utils
        echo "Running unit test: 'autograder'..."
        run_py_unit_autograder
        echo "Running unit test: 'daemon'..."
        run_py_unit_daemon
        ;;
    *)
        echo "$HELP_MESSAGE"
        exit 1
        ;;
esac
