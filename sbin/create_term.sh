#!/bin/bash

# This script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit
fi

# Configuration
CONF_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"/../config

DATABASE_HOST=$(jq -r '.database_host' ${CONF_DIR}/database.json)
DATABASE_USER=$(jq -r '.database_user' ${CONF_DIR}/database.json)
DATABASE_PASS=$(jq -r '.database_password' ${CONF_DIR}/database.json)

# Check that Submitty Master DB exists.
PGPASSWORD=${DATABASE_PASS} psql -h ${DATABASE_HOST} -U ${DATABASE_USER} -lqt | cut -d \| -f 1 | grep -qw submitty
if [[ $? -ne "0" ]] ; then
    echo "ERROR: Submitty master database doesn't exist."
    exit
fi

# Ensure that tables exist within Submitty Master DB.
sql="SELECT count(*) FROM pg_tables WHERE schemaname='public' AND tablename IN ('terms','courses','courses_users','sessions','users');"
table_count=`PGPASSWORD=${DATABASE_PASS} psql -h ${DATABASE_HOST} -U ${DATABASE_USER} -d submitty -tAc "${sql}"`
if [[ $table_count -ne "5" ]] ; then
    echo "ERROR: Submitty Master DB is invalid."
    exit
fi

# Check that there are exactly 4 command line arguments.
if [[ $# -ne "4" ]] ; then
    echo "ERROR: Usage, wrong number of arguments"
    echo "   create_course.sh  <term>  <name of term>  <start date>  <end date>"
    echo "   When entering <name of term>, be sure to escape white space with '\'."
    echo "   Dates must be in 'MM/DD/YYYY' format."
    exit 1
fi

semester=$1
name=$2
start=$3
end=$4

# Check that start and end dates are properly formatted.
regex='^(0[0-9]|1[0-2])/(0[0-9]|[1-2][0-9]|3[0-1])/20[0-9]{2}$'

if ! [[ "$start" =~ $regex ]] ; then
    echo "ERROR: Start date '${start}' invalid.  Use format 'MM/DD/YYYY'."
    exit 1
fi

if ! [[ $end =~ $regex ]] ; then
    echo "ERROR: End date '${end}' invalid.  Use format 'MM/DD/YYYY'."
    exit 1
fi

# Check that start and end dates are actual calendar dates.
date -d $start > /dev/null 2>&1
if [[ $? -ne "0" ]] ; then
    echo "ERROR: Start date '${start}' invalid.  Not a calendar date."
    exit 1
fi

date -d $end > /dev/null 2>&1
if [[ $? -ne "0" ]] ; then
    echo "ERROR: End date '${end}' invalid.  Not a calendar date."
    exit 1
fi
