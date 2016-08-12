#!/bin/bash

########################################################################################################################
########################################################################################################################
# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit
fi

echo -e "\nWelcome to the Submitty Homework Submission Server Configuration\n"

echo "What is the database host? (ex: localhost or csdb3)"
read DATABASE_HOST

echo "What is the database user? (ex: hsdbu)"
read DATABASE_USER

echo "What is the database password for the database user $DATABASE_USER?"
read DATABASE_PASSWORD

echo "What is the url for submission? (ex: http://192.168.56.101/ or https://submitty.cs.rpi.edu/)"
read SUBMISSION_URL

echo "What is the url for the Grading Server? (ex: https://192.168.56.104/ or https://hwgrading.cs.rpi.edu/)"
read TAGRADING_URL

echo "What is the url for the CGI scripts (cgi-bin)? (ex: http://192.168.56.102/ or https://submitty-cgi.cs.rpi.edu/)"
read CGI_URL

echo "What is the SVN path to be used? (ex: svn+ssh://192.168.56.103 or file:///var/lib/svn/csci2600)"
read SVN_PATH


########################################################################################################################
########################################################################################################################

# determine location of SUBMITTY GIT repository
# this script (CONFIGURE.sh) is in the top level directory of the repository
# (this command works even if we run configure from a different directory)
SETUP_SCRIPT_DIRECTORY=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
SUBMITTY_REPOSITORY=`dirname $SETUP_SCRIPT_DIRECTORY`

# recommended (default) directory locations
SUBMITTY_INSTALL_DIR=/usr/local/submitty
SUBMITTY_DATA_DIR=/var/local/submitty

# Log locations
TAGRADING_LOG_PATH=$SUBMITTY_DATA_DIR/tagrading_logs/
AUTOGRADING_LOG_PATH=$SUBMITTY_DATA_DIR/autograding_logs/

# recommended names for special users & groups related to the SUBMITTY system
HWPHP_USER=hwphp
HWCGI_USER=hwcgi
HWCRON_USER=hwcron
HWCRONPHP_GROUP=hwcronphp
COURSE_BUILDERS_GROUP=course_builders

# This value must be at least 60: assumed in INSTALL_SUBMITTY.sh generation of crontab
NUM_UNTRUSTED=60
# FIXME: should check for existence of these users
FIRST_UNTRUSTED_UID=$(id -u untrusted00) # untrusted's user id
FIRST_UNTRUSTED_GID=$(id -g untrusted00) # untrusted's group id

HWCRON_UID=$(id -u ${HWCRON_USER})       # hwcron's user id
HWCRON_GID=$(id -g ${HWCRON_USER})       # hwcron's group id
HWPHP_UID=$(id -u ${HWPHP_USER})         # hwphp's user id
HWPHP_GID=$(id -g ${HWPHP_USER})         # hwphp's group id
HWCGI_UID=$(id -u ${HWCGI_USER})
HWCGI_GID=$(id -g ${HWCGI_USER})

# adjust this number depending on the # of processors
# available on your hardware
MAX_INSTANCES_OF_GRADE_STUDENTS=15

# if queue is empty, wait this long before checking the queue again
GRADE_STUDENTS_IDLE_SECONDS=5
# each grade_students.sh process should idle for this long total
# before terminating the process
GRADE_STUDENTS_IDLE_TOTAL_MINUTES=16

# how often should the cron job launch a new grade_students.sh script?
# 4 starts per hour  = every 15 minutes
# 12 starts per hour = every 5 minutes
# 15 starts per hour = every 4 minutes
#GRADE_STUDENTS_STARTS_PER_HOUR=12
GRADE_STUDENTS_STARTS_PER_HOUR=20

########################################################################################################################
########################################################################################################################

# confirm that the uid/gid of the untrusted users are sequential
which_untrusted=0
while [ $which_untrusted -lt $NUM_UNTRUSTED ]; do
    an_untrusted_user=$(printf "untrusted%.2d" $which_untrusted)
    if [ $(id -u $an_untrusted_user) -ne $(($FIRST_UNTRUSTED_UID+$which_untrusted)) ] ; then
		echo "CONFIGURATION ERROR: untrusted UID not sequential: $an_untrusted_user"
		exit
	fi
    if [ $(id -g $an_untrusted_user) -ne $(($FIRST_UNTRUSTED_GID+$which_untrusted)) ] ; then
		echo "CONFIGURATION ERROR: untrusted GID not sequential: $an_untrusted_user"
	    echo "AN UNTRUSTED $an_untrusted_user"
		exit
    fi
    let which_untrusted=which_untrusted+1
done

########################################################################################################################
########################################################################################################################

# FIXME: DO SOME ERROR CHECKING ON THE VARIABLE SETTINGS
#        (variables are different from each other, directories valid/exist/writeable, etc)

# make the installation setup directory
rm -rf $SUBMITTY_INSTALL_DIR/.setup
mkdir -p $SUBMITTY_INSTALL_DIR/.setup
chown root:root $SUBMITTY_INSTALL_DIR/.setup
chmod 700 $SUBMITTY_INSTALL_DIR/.setup

# copy the submitty installation script
rm $SUBMITTY_REPOSITORY/.setup/INSTALL_SUBMITTY.sh > /dev/null 2>&1
cp $SUBMITTY_REPOSITORY/.setup/INSTALL_SUBMITTY_template.sh $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh

# set the permissions of this file
chown root:root $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh
chmod 500 $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh

# fillin the necessary variables
sed -i -e "s|__CONFIGURE__FILLIN__SUBMITTY_REPOSITORY__|$SUBMITTY_REPOSITORY|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh
sed -i -e "s|__CONFIGURE__FILLIN__SUBMITTY_INSTALL_DIR__|$SUBMITTY_INSTALL_DIR|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh
sed -i -e "s|__CONFIGURE__FILLIN__SUBMITTY_DATA_DIR__|$SUBMITTY_DATA_DIR|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh
sed -i -e "s|__CONFIGURE__FILLIN__SVN_PATH__|$SVN_PATH|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh
sed -i -e "s|__CONFIGURE__FILLIN__HWCGI_USER__|$HWCGI_USER|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh
sed -i -e "s|__CONFIGURE__FILLIN__HWPHP_USER__|$HWPHP_USER|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh
sed -i -e "s|__CONFIGURE__FILLIN__HWCRON_USER__|$HWCRON_USER|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh
sed -i -e "s|__CONFIGURE__FILLIN__HWCRONPHP_GROUP__|$HWCRONPHP_GROUP|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh
sed -i -e "s|__CONFIGURE__FILLIN__COURSE_BUILDERS_GROUP__|$COURSE_BUILDERS_GROUP|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh

sed -i -e "s|__CONFIGURE__FILLIN__NUM_UNTRUSTED__|$NUM_UNTRUSTED|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh
sed -i -e "s|__CONFIGURE__FILLIN__FIRST_UNTRUSTED_UID__|$FIRST_UNTRUSTED_UID|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh
sed -i -e "s|__CONFIGURE__FILLIN__FIRST_UNTRUSTED_GID__|$FIRST_UNTRUSTED_GID|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh

sed -i -e "s|__CONFIGURE__FILLIN__HWCRON_UID__|$HWCRON_UID|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh
sed -i -e "s|__CONFIGURE__FILLIN__HWCRON_GID__|$HWCRON_GID|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh
sed -i -e "s|__CONFIGURE__FILLIN__HWPHP_UID__|$HWPHP_UID|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh
sed -i -e "s|__CONFIGURE__FILLIN__HWPHP_GID__|$HWPHP_GID|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh

sed -i -e "s|__CONFIGURE__FILLIN__DATABASE_HOST__|$DATABASE_HOST|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh
sed -i -e "s|__CONFIGURE__FILLIN__DATABASE_USER__|$DATABASE_USER|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh
sed -i -e "s|__CONFIGURE__FILLIN__DATABASE_PASSWORD__|$DATABASE_PASSWORD|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh

sed -i -e "s|__CONFIGURE__FILLIN__TAGRADING_URL__|$TAGRADING_URL|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh
sed -i -e "s|__CONFIGURE__FILLIN__SUBMISSION_URL__|$SUBMISSION_URL|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh
sed -i -e "s|__CONFIGURE__FILLIN__CGI_URL__|$CGI_URL|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh
sed -i -e "s|__CONFIGURE__FILLIN__TAGRADING_LOG_PATH__|$TAGRADING_LOG_PATH|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh

sed -i -e "s|__CONFIGURE__FILLIN__AUTOGRADING_LOG_PATH__|$AUTOGRADING_LOG_PATH|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh


sed -i -e "s|__CONFIGURE__FILLIN__MAX_INSTANCES_OF_GRADE_STUDENTS__|$MAX_INSTANCES_OF_GRADE_STUDENTS|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh
sed -i -e "s|__CONFIGURE__FILLIN__GRADE_STUDENTS_IDLE_SECONDS__|$GRADE_STUDENTS_IDLE_SECONDS|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh
sed -i -e "s|__CONFIGURE__FILLIN__GRADE_STUDENTS_IDLE_TOTAL_MINUTES__|$GRADE_STUDENTS_IDLE_TOTAL_MINUTES|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh
sed -i -e "s|__CONFIGURE__FILLIN__GRADE_STUDENTS_STARTS_PER_HOUR__|$GRADE_STUDENTS_STARTS_PER_HOUR|g" $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh


# FIXME: Add some error checking to make sure those values were filled in correctly

########################################################################################################################
########################################################################################################################

echo -e "Configuration completed.  Now you may run the installation script"
echo -e "   sudo $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh"
echo -e "          or"
echo -e "   sudo $SUBMITTY_INSTALL_DIR/.setup/INSTALL_SUBMITTY.sh clean\n\n"
