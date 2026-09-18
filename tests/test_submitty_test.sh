#!/bin/bash
set -euo pipefail

REPOSITORY_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SUBMITTY_TEST="$REPOSITORY_ROOT/.setup/SUBMITTY_TEST.sh"
TEMPORARY_DIRECTORY="$(mktemp -d)"
DOCKER_CAPTURE="$TEMPORARY_DIRECTORY/docker-arguments"
export DOCKER_CAPTURE

cleanup() {
    rm -rf "$TEMPORARY_DIRECTORY"
}
trap cleanup EXIT

cat > "$TEMPORARY_DIRECTORY/docker" <<'EOF'
#!/bin/bash
set -euo pipefail
printf '%s\n' "$@" > "${DOCKER_CAPTURE:?}"
EOF
chmod +x "$TEMPORARY_DIRECTORY/docker"

assert_equal() {
    local expected="$1"
    local actual="$2"
    local description="$3"

    if [ "$actual" != "$expected" ]; then
        printf 'FAIL: %s\nExpected:\n%s\nActual:\n%s\n' "$description" "$expected" "$actual" >&2
        exit 1
    fi
}

help_output="$(bash "$SUBMITTY_TEST" help)"
expected_usage='                usage: twig-lint [--format FORMAT] [--show-deprecations] [--] [<filename>...]'
if ! grep -Fqx -- "$expected_usage" <<< "$help_output"; then
    printf 'FAIL: Twig lint help does not contain the Symfony 6.4 usage.\n' >&2
    exit 1
fi
if grep -Fq -- '--excludes' <<< "$help_output"; then
    printf 'FAIL: Twig lint help advertises the unavailable --excludes option.\n' >&2
    exit 1
fi

PATH="$TEMPORARY_DIRECTORY:$PATH" bash "$SUBMITTY_TEST" twig-lint > /dev/null
actual_default="$(tail -n 3 "$DOCKER_CAPTURE")"
expected_default="$(printf '%s\n' composer run-script lint:twig)"
assert_equal "$expected_default" "$actual_default" 'default Twig lint command'

PATH="$TEMPORARY_DIRECTORY:$PATH" bash "$SUBMITTY_TEST" \
    twig-lint --format json --show-deprecations -- 'app/templates/path with spaces.twig' > /dev/null
actual_optional="$(tail -n 9 "$DOCKER_CAPTURE")"
expected_optional="$(printf '%s\n' \
    composer run-script lint:twig -- --format json --show-deprecations -- \
    'app/templates/path with spaces.twig')"
assert_equal "$expected_optional" "$actual_optional" 'Twig lint optional argument forwarding'

printf 'PASS: submitty_test Twig lint help and argument forwarding\n'
