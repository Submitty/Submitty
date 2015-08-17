#!/bin/bash

########################################################################################################################
########################################################################################################################

# determine location of HSS GIT repository
# this script (CONFIGURE.sh) is in the top level directory of the repository
# (this command works even if we run configure from a different directory)
HSS_REPOSITORY=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )

# assume the TA grading repo is in the same location
# NOTE: eventually the TA grading repo will be merged into the main repo
TAGRADING_REPOSITORY=$HSS_REPOSITORY/../GIT_CHECKOUT_TAgrading

# recommended (default) directory locations
HSS_INSTALL_DIR=/usr/local/hss
HSS_DATA_DIR=/var/local/hss

#FIXME: When multiple courses use SVN, this will need to be updated...
SVN_PATH=svn+ssh://csci2600svn.cs.rpi.edu/var/lib/svn/csci2600

# recommended names for special users & groups related to the HSS system
HWPHP_USER=hwphp
HWCRON_USER=hwcron
HWCRONPHP_GROUP=hwcronphp
INSTRUCTORS_GROUP=instructors

UNTRUSTED_UID=`id -u untrusted` # untrusted's user id 
UNTRUSTED_GID=`id -g untrusted` # untrusted's group id
HWCRON_UID=`id -u hwcron`       # hwcron's user id 
HWCRON_GID=`id -g hwcron`       # hwcron's group id

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

# FIXME: DO SOME ERROR CHECKING ON THE VARIABLE SETTINGS
#        (variables are different from each other, directories valid/exist/writeable, etc)


# copy the installation script
cp $HSS_REPOSITORY/bin/INSTALL_template.sh $HSS_REPOSITORY/INSTALL.sh

# fillin the necessary variables 
sed -i -e "s|__CONFIGURE__FILLIN__HSS_REPOSITORY__|$HSS_REPOSITORY|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__TAGRADING_REPOSITORY__|$TAGRADING_REPOSITORY|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__HSS_INSTALL_DIR__|$HSS_INSTALL_DIR|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__HSS_DATA_DIR__|$HSS_DATA_DIR|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__SVN_PATH__|$SVN_PATH|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__HWPHP_USER__|$HWPHP_USER|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__HWCRON_USER__|$HWCRON_USER|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__HWCRONPHP_GROUP__|$HWCRONPHP_GROUP|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__INSTRUCTORS_GROUP__|$INSTRUCTORS_GROUP|g" $HSS_REPOSITORY/INSTALL.sh

sed -i -e "s|__CONFIGURE__FILLIN__UNTRUSTED_UID__|$UNTRUSTED_UID|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__UNTRUSTED_GID__|$UNTRUSTED_GID|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__HWCRON_UID__|$HWCRON_UID|g" $HSS_REPOSITORY/INSTALL.sh
sed -i -e "s|__CONFIGURE__FILLIN__HWCRON_GID__|$HWCRON_GID|g" $HSS_REPOSITORY/INSTALL.sh

# FIXME: Add some error checking to make sure those values were filled in correctly

# Copy account maintenance scripts unless they exist
mkdir -p $HSS_INSTALL_DIR/bin
[ -f $HSS_INSTALL_DIR/bin/rcsonly.pl ] || cp $HSS_REPOSITORY/Docs/Sample_bin/rcsonly.pl $HSS_INSTALL_DIR/bin/rcsonly.pl
[ -f $HSS_INSTALL_DIR/bin/new.svnuser.pl ] || cp $HSS_REPOSITORY/Docs/Sample_bin/rcsonly.pl $HSS_INSTALL_DIR/bin/new.svnuser.pl
mkdir -p /root/bin
touch /var/local/hss/instructors/rcslist
touch /var/local/hss/instructors/svnlist
[ -f /root/bin/top.txt ] || cp $HSS_REPOSITORY/Docs/Sample_bin/top.txt /root/bin/top.txt
[ -f /root/bin/bottom.txt ] || cp $HSS_REPOSITORY/Docs/Sample_bin/bottom.txt /root/bin/bottom.txt
[ -f /root/bin/gen.middle ] || cp $HSS_REPOSITORY/Docs/Sample_bin/gen.middle /root/bin/gen.middle
[ -f /root/bin/regen.apache ] || cp $HSS_REPOSITORY/Docs/Sample_bin/regen.apache /root/bin/regen.apache
chgrp instructors /root/bin/top.txt /root/bin/bottom.txt /root/bin/gen.middle /root/bin/regen.apache /var/local/hss/instructors/rcslist /var/local/hss/instructors/svnlist
chmod 750 /root/bin/gen.middle /root/bin/regen.apache
chmod 660 /var/local/hss/instructors/rcslist /var/local/hss/instructors/svnlist

# Prepare subversion
[ -f /etc/init.d/svnserve ] || cp $HSS_REPOSITORY/Docs/sample_svnserve /etc/init.d/svnserve
chmod +x /etc/init.d/svnserve
update-rc.d svnserve defaults

########################################################################################################################
########################################################################################################################

echo -e "Configuration completed.  Now you may run the installation script"
echo -e "   $HSS_REPOSITORY/INSTALL.sh"
echo -e "          or"
echo -e "   $HSS_REPOSITORY/INSTALL.sh clean\n\n"
