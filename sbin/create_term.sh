#!/bin/bash

set -e

# This script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit 3
fi

# Configuration
CONF_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"/../config

DATABASE_HOST=$(jq -r '.database_host' ${CONF_DIR}/database.json)
DATABASE_PORT=$(jq -r '.database_port' ${CONF_DIR}/database.json)
DATABASE_USER=$(jq -r '.database_user' ${CONF_DIR}/database.json)
DATABASE_PASS=$(jq -r '.database_password' ${CONF_DIR}/database.json)

CONN_STRING="-h ${DATABASE_HOST} -U ${DATABASE_USER}"
if [ -d ${DATABASE_HOST} ]; then
    CONN_STRING="${CONN_STRING} -p ${DATABASE_PORT}"
fi

# Use set +e to allow capturing of the exit code
set +e
# Check that Submitty Master DB exists.
PGPASSWORD=${DATABASE_PASS} psql ${CONN_STRING} -lqt | cut -d \| -f 1 | grep -qw submitty
if [[ $? -ne "0" ]] ; then
    echo "ERROR: Submitty master database doesn't exist."
    exit 4
fi

# Ensure that terms table exists within Submitty Master DB.
sql="SELECT count(*) FROM pg_tables WHERE schemaname='public' AND tablename IN ('terms');"
table_count=`PGPASSWORD=${DATABASE_PASS} psql ${CONN_STRING} -d submitty -tAc "${sql}"`
if [[ $table_count -ne "1" ]] ; then
    echo "ERROR: Submitty Master DB is invalid."
    exit 4
fi
set -e 

amend=0
if [ "${1}" = "-a" ] || [ "${1}" = "--amend" ]; then
    shift
    amend=1
fi

# Check that there are exactly 4 command line arguments.
if [[ $# -ne "4" ]] ; then
    echo "Usage: create_term.sh  [-a|--amend] <term>  '<name of term>'  <start date>  <end date>"
    echo "  <name of term> must be properly escaped."
    echo "  <start date> and <end date> must be in 'MM/DD/YYYY' format."
    echo "  use --amend to also allow amending an existing term"
    exit 5
fi

semester=$1
name=$2
start=$3
end=$4

# Validate that start and end dates are properly formatted (MM/DD/YYY).
regex='^(0[0-9]|1[0-2])/(0[0-9]|[1-2][0-9]|3[0-1])/20[0-9]{2}$'

if ! [[ $start =~ $regex ]] ; then
    echo "ERROR: Start date '${start}' invalid.  Use format 'MM/DD/YYYY'."
    exit 5
fi

if ! [[ $end =~ $regex ]] ; then
    echo "ERROR: End date '${end}' invalid.  Use format 'MM/DD/YYYY'."
    exit 5
fi

# Use set +e to allow capturing of the exit code
set +e
# Validate that start and end dates are actual calendar dates.
date -d $start > /dev/null 2>&1
if [[ $? -ne "0" ]] ; then
    echo "ERROR: Start date '${start}' invalid.  Not a calendar date."
    exit 5
fi

date -d $end > /dev/null 2>&1
if [[ $? -ne "0" ]] ; then
    echo "ERROR: End date '${end}' invalid.  Not a calendar date."
    exit 5
fi

# INSERT new term into master DB
insert_string="
INSERT INTO terms (term_id, name, start_date, end_date)
VALUES ('${semester}', '${name}', TO_DATE('${start}', 'MM/DD/YYYY'), TO_DATE('${end}', 'MM/DD/YYYY'))"

if [ ${amend} -eq 1 ]; then
    insert_string+="
ON CONFLICT (term_id)
DO UPDATE SET name=EXCLUDED.name, start_date=EXCLUDED.start_date, end_date=EXCLUDED.end_date"
fi

PGPASSWORD=${DATABASE_PASS} psql ${CONN_STRING} -d submitty -qc "${insert_string};"

if [[ $? -ne "0" ]] ; then
    echo "ERROR: Failed to INSERT $([ ${amend} -eq 1 ] && echo 'or AMEND ')new term into master DB."
    exit 6
fi
set -e

echo "'${semester}' term has been INSERTed $([ ${amend} -eq 1 ] && echo 'or AMENDed ')into the master DB."
echo "You may now create courses for the '${semester}' term."
