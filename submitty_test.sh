#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
IMAGE_NAME="submitty_test"

# handle rebuild vs run
if [ "${1:-}" == "rebuild" ]; then
    echo "Rebuilding Docker image '$IMAGE_NAME' from scratch..."
    docker build --pull --no-cache -t "$IMAGE_NAME" "$SCRIPT_DIR"
    exit 0
else
    # build docker image
    echo "Setting up Docker image '$IMAGE_NAME'..."
    docker build -t "$IMAGE_NAME" "$SCRIPT_DIR"
fi

# runs a command in the container with a given working directory
run_in_container() {
    local workdir="$1"
    shift
    # check if the environment supports -t (in this case, just used for color output)
    local terminal_flag=""
    [ -t 0 ] && terminal_flag="-t"
    docker run --rm $terminal_flag -u "$(id -u):$(id -g)" -e HOME=/tmp \
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

run_py_lint() {
    parse_args "${@:2}"
    if [ ${#ARGS[@]} -gt 0 ]; then
        run_in_container /submitty python3 -m flake8 "${ARGS[@]}"
        run_in_container /submitty python3 -m pylint "${ARGS[@]}"
    else
        run_in_container /submitty python3 -m flake8
        run_in_container /submitty python3 -m pylint --recursive=y .
    fi
}

run_py_unit() {
    parse_args "${@:2}"

    # install in editable mode rather than local user
    run_in_container /submitty/python_submitty_utils pip3 install --user -e .

    if [ ${#ARGS[@]} -gt 0 ]; then
        if [ "${ARGS[0]}" == "utils" ]; then
            run_in_container /submitty/python_submitty_utils coverage run -m unittest discover "${ARGS[@]:1}"
        elif [ "${ARGS[0]}" == "migration" ]; then
            run_in_container /submitty/migration coverage run -m unittest discover "${ARGS[@]:1}"
        elif [ "${ARGS[0]}" == "autograder" ]; then
            run_in_container /submitty/autograder coverage run -m unittest discover "${ARGS[@]:1}"
        elif [ "${ARGS[0]}" == "daemon" ]; then
            run_in_container /submitty/sbin/submitty_daemon_jobs coverage run -m unittest discover tests -t . "${ARGS[@]:1}"
        else
            echo "Unknown python unit test: ${ARGS[0]}. Use: utils, migration, autograder, or daemon"
            exit 1
        fi
    else
        run_in_container /submitty/python_submitty_utils coverage run -m unittest discover
        run_in_container /submitty/migration coverage run -m unittest discover
        run_in_container /submitty/autograder coverage run -m unittest discover
        run_in_container /submitty/sbin/submitty_daemon_jobs coverage run -m unittest discover tests -t .
    fi
}
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
          py-lint   : run flake8 and pylint [option: specific_file.py]
          py-unit   : run all python unit tests [option: utils|migration|autograder|daemon]
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
elif [ "$1" == "py-lint" ]; then
    run_py_lint "$@"
elif [ "$1" == "py-unit" ]; then
    run_py_unit "$@"
else
    echo "Unknown test type: $1
        use rebuild, phpstan, phpcs, php-lint, php-unit, js-lint, css-lint, py-lint, py-unit
        or help for detail"
fi
