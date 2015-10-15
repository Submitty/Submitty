#!/bin/bash

########################################################################################################################
########################################################################################################################
# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit
fi

if [[ "$#" -eq 0 ]] ; then
    echo -e "\n\nWelcome to the Homework Submission Server (HSS) Configuration\n\n"
    # defaults above are fine
else
	echo -e "\n\nERROR: This script should be run with zero arguments\n\n"
	exit
fi

echo "What is the database host?"
read DATABASE_HOST

echo "What is the database user?"
read DATABASE_USER

echo "What is the database password for the database user $DATABASE_USER?"
read DATABASE_PASSWORD

echo "What is the url for the Grading Server?"
read TAGRADING_URL

echo "What is the SVN path to be used?"
# SVN_PATH=svn+ssh://csci2600svn.cs.rpi.edu/var/lib/svn/csci2600
read SVN_PATH

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

# Log locations
TAGRADING_LOG_PATH=$HSS_DATA_DIR/tagrading_logs/
AUTOGRADING_LOG_PATH=$HSS_DATA_DIR/autograding_logs/

# recommended names for special users & groups related to the HSS system
HWPHP_USER=hwphp
HWCRON_USER=hwcron
HWCRONPHP_GROUP=hwcronphp
COURSE_BUILDERS_GROUP=course_builders

UNTRUSTED_UID=`id -u untrusted` # untrusted's user id
UNTRUSTED_GID=`id -g untrusted` # untrusted's group id
HWCRON_UID=`id -u hwcron`       # hwcron's user id
HWCRON_GID=`id -g hwcron`       # hwcron's group id
HWPHP_UID=`id -u hwphp`         # hwphp's user id
HWPHP_GID=`id -g hwphp`         # hwphp's group id

########################################################################################################################
########################################################################################################################

# FIXME: DO SOME ERROR CHECKING ON THE VARIABLE SETTINGS
#        (variables are different from each other, directories valid/exist/writeable, etc)

# copy the installation script
rm $HSS_REPOSITORY/INSTALL.sh > /dev/null 2>&1
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

sed -i -e "s|__CONFIGURE__FILLIN__UNTRUSTED_UID__|$UNTRUSTED_UID|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__UNTRUSTED_GID__|$UNTRUSTED_GID|g" $HSS_REPOSITORY/INSTALL.sh
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



# FIXME: Add some error checking to make sure those values were filled in correctly

########################################################################################################################
########################################################################################################################

echo -e "Configuration completed.  Now you may run the installation script"
echo -e "   sudo $HSS_REPOSITORY/INSTALL.sh"
echo -e "          or"
echo -e "   sudo $HSS_REPOSITORY/INSTALL.sh clean\n\n"
