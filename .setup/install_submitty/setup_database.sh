#!/usr/bin/env bash
set -ev

for cli_arg in "$@"
do
    if [[ $cli_arg =~ ^config=.* ]]; then
        SUBMITTY_CONFIG_DIR="$(readlink -f "$(echo "$cli_arg" | cut -f2 -d=)")"
    fi
done

if [ -z "${SUBMITTY_CONFIG_DIR}" ]; then
    echo "ERROR: This script requires a config dir argument"
    echo "Usage: ${BASH_SOURCE[0]} config=<config dir>"
    exit 1
fi

SUBMITTY_REPOSITORY=$(jq -r '.submitty_repository' "${SUBMITTY_CONFIG_DIR:?}/submitty.json")
################################################################################################################
################################################################################################################
# RUN THE SYSTEM AND DATABASE MIGRATIONS

# shellcheck disable=SC1091
source "${SUBMITTY_REPOSITORY:?}/.setup/install_submitty/get_globals.sh" "config=${SUBMITTY_CONFIG_DIR:?}"

echo -e 'Checking for system and database migrations'

mkdir -p "${SUBMITTY_INSTALL_DIR}/migrations"

rsync -rtz "${SUBMITTY_REPOSITORY}/migration/migrator/migrations" "${SUBMITTY_INSTALL_DIR}"
chown root:root "${SUBMITTY_INSTALL_DIR}/migrations"
chmod 550 -R "${SUBMITTY_INSTALL_DIR}/migrations"

python3 "${SUBMITTY_REPOSITORY}/migration/run_migrator.py" -e system -e master -e course migrate

################################################################################################################
################################################################################################################
# VALIDATE DATABASE SUPERUSERS
DATABASE_FILE="$SUBMITTY_INSTALL_DIR/config/database.json"
DATABASE_HOST=$(jq -r '.database_host' "$DATABASE_FILE")
DATABASE_PORT=$(jq -r '.database_port' "$DATABASE_FILE")
GLOBAL_DBUSER=$(jq -r '.database_user' "$DATABASE_FILE")
GLOBAL_DBUSER_PASS=$(jq -r '.database_password' "$DATABASE_FILE")
COURSE_DBUSER=$(jq -r '.database_course_user' "$DATABASE_FILE")

DB_CONN="-h ${DATABASE_HOST} -U ${GLOBAL_DBUSER}"
if [ ! -d "${DATABASE_HOST}" ]; then
    DB_CONN="${DB_CONN} -p ${DATABASE_PORT}"
fi


# shellcheck disable=SC2086
CHECK=$(PGPASSWORD=${GLOBAL_DBUSER_PASS} psql ${DB_CONN} -d submitty -tAc "SELECT rolsuper FROM pg_authid WHERE rolname='$GLOBAL_DBUSER'")

if [ "$CHECK" == "f" ]; then
    echo "ERROR: Database Superuser check failed! Master dbuser found to not be a superuser."
    exit 1
fi

# shellcheck disable=SC2086
CHECK=$(PGPASSWORD=${GLOBAL_DBUSER_PASS} psql ${DB_CONN} -d submitty -tAc "SELECT rolsuper FROM pg_authid WHERE rolname='$COURSE_DBUSER'")

if [ "$CHECK" == "t" ]; then
    echo "ERROR: Database Superuser check failed! Course dbuser found to be a superuser."
    exit 1
fi
