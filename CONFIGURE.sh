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

# determine location of HSS GIT repository
# this script (CONFIGURE.sh) is in the top level directory of the repository
# (this command works even if we run configure from a different directory)
HSS_REPOSITORY=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )

# assume the TA grading repo is in the same location
# NOTE: eventually the TA grading repo will be merged into the main repo
#TAGRADING_REPOSITORY=$HSS_REPOSITORY/../GIT_CHECKOUT_TAgrading
TAGRADING_REPOSITORY=$HSS_REPOSITORY/TAGradingServer

# recommended (default) directory locations
HSS_INSTALL_DIR=/usr/local/hss
HSS_DATA_DIR=/var/local/hss

#FIXME: When multiple courses use SVN, this will need to be updated...
SVN_PATH=svn+ssh://csci2600svn.cs.rpi.edu/var/lib/svn/csci2600

# recommended names for special users & groups related to the HSS system
HWPHP_USER=hwphp
HWCRON_USER=hwcron
HWCRONPHP_GROUP=hwcronphp
COURSE_BUILDERS_GROUP=course_builders
DATABASE_USER=hsdbu

# This value must be at least 60: assumed in INSTALL.sh generation of crontab
NUM_UNTRUSTED=60
# FIXME: should check for existence of these users
FIRST_UNTRUSTED_UID=`id -u untrusted00` # untrusted's user id 
FIRST_UNTRUSTED_GID=`id -g untrusted00` # untrusted's group id

HWCRON_UID=`id -u hwcron`       # hwcron's user id 
HWCRON_GID=`id -g hwcron`       # hwcron's group id
HWPHP_UID=`id -u hwphp`         # hwphp's user id 
HWPHP_GID=`id -g hwphp`         # hwphp's group id


# adjust this number depending on the # of processors
# available on your hardware
MAX_INSTANCES_OF_GRADE_STUDENTS=8

# if queue is empty, wait this long before checking the queue again
GRADE_STUDENTS_IDLE_SECONDS=5
# each grade_students.sh process should idle for this long total
# before terminating the process
GRADE_STUDENTS_IDLE_TOTAL_MINUTES=16

# how often should the cron job launch a new grade_students.sh script?
# 4 starts per hour  = every 15 minutes
# 12 starts per hour = every 5 minutes
# 15 starts per hour = every 4 minutes
GRADE_STUDENTS_STARTS_PER_HOUR=12


########################################################################################################################
########################################################################################################################

if [[ "$#" -eq 0 ]] ; then
    echo -e "\n\nWelcome to the Homework Submission Server (HSS) Default Configuration"
    echo -e "(rerun this script with a single argument "custom" to customize the installation\n"
    # defaults above are fine
else
    if [[ "$#" -ne 1 || $1 != "custom" ]] ; then
	echo -e "\n\nERROR: This script should be run with zero arguments or a single argument "custom"\n\n"
	exit
    fi
    echo -e "\n\nWelcome to the Homework Submission Server (HSS) Interactive Custom Configuration"
    # FIXME: query user to ask if they would like to change the defaults above
    echo -e "Sorry, the interactive script is not written yet....  you are stuck with the defaults.\n"
fi


########################################################################################################################
########################################################################################################################

# confirm that the uid/gid of the untrusted users are sequential
which_untrusted=0
while [ $which_untrusted -lt $NUM_UNTRUSTED ]; do
    an_untrusted_user=`printf "untrusted%.2d" $which_untrusted`
    if [ `id -u $an_untrusted_user` -ne $(($FIRST_UNTRUSTED_UID+$which_untrusted)) ] ; then
	echo "CONFIGURATION ERROR: untrusted UID not sequential: $an_untrusted_user"
	exit
    fi
    if [ `id -g $an_untrusted_user` -ne $(($FIRST_UNTRUSTED_GID+$which_untrusted)) ] ; then
	echo "CONFIGURATION ERROR: untrusted GID not sequential: $an_untrusted_user"
    echo "AN UNTRUSTED $an_untrusted_user"
	exit
    fi
    let which_untrusted=which_untrusted+1
done

########################################################################################################################
########################################################################################################################

echo "What is the database password for the database user $DATABASE_USER?"
read DATABASE_PASSWORD


# assumptions

DATABASE_HOST=csdb3
TAGRADING_URL=https://hwgrading.cs.rpi.edu/
TAGRADING_LOG_PATH=$HSS_DATA_DIR/tagrading_logs/

AUTOGRADING_LOG_PATH=$HSS_DATA_DIR/autograding_logs/


########################################################################################################################
########################################################################################################################

# FIXME: DO SOME ERROR CHECKING ON THE VARIABLE SETTINGS
#        (variables are different from each other, directories valid/exist/writeable, etc)


# copy the installation script
cp $HSS_REPOSITORY/bin/INSTALL_template.sh $HSS_REPOSITORY/INSTALL.sh


# set the permissions of this file 
chown root:root $HSS_REPOSITORY/INSTALL.sh
chmod 500 $HSS_REPOSITORY/INSTALL.sh


# fillin the necessary variables 
sed -i -e "s|__CONFIGURE__FILLIN__HSS_REPOSITORY__|$HSS_REPOSITORY|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__TAGRADING_REPOSITORY__|$TAGRADING_REPOSITORY|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__HSS_INSTALL_DIR__|$HSS_INSTALL_DIR|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__HSS_DATA_DIR__|$HSS_DATA_DIR|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__SVN_PATH__|$SVN_PATH|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__HWPHP_USER__|$HWPHP_USER|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__HWCRON_USER__|$HWCRON_USER|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__HWCRONPHP_GROUP__|$HWCRONPHP_GROUP|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__COURSE_BUILDERS_GROUP__|$COURSE_BUILDERS_GROUP|g" $HSS_REPOSITORY/INSTALL.sh

sed -i -e "s|__CONFIGURE__FILLIN__NUM_UNTRUSTED__|$NUM_UNTRUSTED|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__FIRST_UNTRUSTED_UID__|$FIRST_UNTRUSTED_UID|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__FIRST_UNTRUSTED_GID__|$FIRST_UNTRUSTED_GID|g" $HSS_REPOSITORY/INSTALL.sh

sed -i -e "s|__CONFIGURE__FILLIN__HWCRON_UID__|$HWCRON_UID|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__HWCRON_GID__|$HWCRON_GID|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__HWPHP_UID__|$HWPHP_UID|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__HWPHP_GID__|$HWPHP_GID|g" $HSS_REPOSITORY/INSTALL.sh

sed -i -e "s|__CONFIGURE__FILLIN__DATABASE_HOST__|$DATABASE_HOST|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__DATABASE_USER__|$DATABASE_USER|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__DATABASE_PASSWORD__|$DATABASE_PASSWORD|g" $HSS_REPOSITORY/INSTALL.sh

sed -i -e "s|__CONFIGURE__FILLIN__TAGRADING_URL__|$TAGRADING_URL|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__TAGRADING_LOG_PATH__|$TAGRADING_LOG_PATH|g" $HSS_REPOSITORY/INSTALL.sh

sed -i -e "s|__CONFIGURE__FILLIN__AUTOGRADING_LOG_PATH__|$AUTOGRADING_LOG_PATH|g" $HSS_REPOSITORY/INSTALL.sh


sed -i -e "s|__CONFIGURE__FILLIN__MAX_INSTANCES_OF_GRADE_STUDENTS__|$MAX_INSTANCES_OF_GRADE_STUDENTS|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__GRADE_STUDENTS_IDLE_SECONDS__|$GRADE_STUDENTS_IDLE_SECONDS|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__GRADE_STUDENTS_IDLE_TOTAL_MINUTES__|$GRADE_STUDENTS_IDLE_TOTAL_MINUTES|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__GRADE_STUDENTS_STARTS_PER_HOUR__|$GRADE_STUDENTS_STARTS_PER_HOUR|g" $HSS_REPOSITORY/INSTALL.sh


# FIXME: Add some error checking to make sure those values were filled in correctly

########################################################################################################################
########################################################################################################################

echo -e "Configuration completed.  Now you may run the installation script"
echo -e "   sudo $HSS_REPOSITORY/INSTALL.sh"
echo -e "          or"
echo -e "   sudo $HSS_REPOSITORY/INSTALL.sh clean\n\n"
