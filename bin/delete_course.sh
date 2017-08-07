#!/bin/bash

########################################################################################################################
########################################################################################################################
# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit
fi

########################################################################################################################
########################################################################################################################

# these variables will be replaced by INSTALL.sh

SUBMITTY_INSTALL_DIR=__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__
SUBMITTY_DATA_DIR=__INSTALL__FILLIN__SUBMITTY_DATA_DIR__
SUBMISSION_URL=__INSTALL__FILLIN__SUBMISSION_URL__

HWPHP_USER=__INSTALL__FILLIN__HWPHP_USER__
HWCRON_USER=__INSTALL__FILLIN__HWCRON_USER__
HWCGI_USER=__INSTALL__FILLIN__HWCGI_USER__

COURSE_BUILDERS_GROUP=__INSTALL__FILLIN__COURSE_BUILDERS_GROUP__

DATABASE_HOST=__INSTALL__FILLIN__DATABASE_HOST__
DATABASE_USER=__INSTALL__FILLIN__DATABASE_USER__
DATABASE_PASS='__INSTALL__FILLIN__DATABASE_PASSWORD__'

########################################################################################################################
########################################################################################################################

if [[ $# -ne "2" ]] ; then
    echo "ERROR: Usage, wrong number of arguments"
    echo "   create_course.sh  <semester>  <course>"
    exit
fi

semester=$1
course=$2

echo -e "\DELETING COURSE:"
echo -e "  semester:     $semester"
echo -e "  course:       $course"
echo

read -p "Are you sure? " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]
then
    # handle exits from shell or function but don't exit interactive shell
    [[ "$0" = "$BASH_SOURCE" ]] && exit 1 || return 1
fi

########################################################################################################################
########################################################################################################################

course_dir=$SUBMITTY_DATA_DIR/courses/$semester/$course
database_name=submitty_${semester}_${course}

########################################################################################################################
########################################################################################################################

echo "Deleting course directory"
rm -rf ${course_dir}

########################################################################################################################
########################################################################################################################

echo "Deleting course database"
PGPASSWORD=${DATABASE_PASS} psql -h ${DATABASE_HOST} -U ${DATABASE_USER} -d postgres -c "DROP DATABASE ${database_name}"
PGPASSWORD=${DATABASE_PASS} psql -h ${DATABASE_HOST} -U ${DATABASE_USER} -d submitty -c "DELETE FROM courses_users WHERE semester='${semester}' AND course='${course}'; DELETE FROM courses WHERE semester='${semester}' AND course='${course}';"

########################################################################################################################
########################################################################################################################

echo -e "COURSE DELETED"
