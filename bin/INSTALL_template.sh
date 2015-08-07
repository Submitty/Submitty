#!/bin/bash


########################################################################################################################
########################################################################################################################
# this script must be run by root or sudo 
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit
fi


# defaults 
# FIXME: allow these to be configured by the CONFIGURE.sh script
# (E.g., CONFIGURE.sh will overwrite these variables in a copy of this installation script)
HSS_INSTALL_DIR=/usr/local/hss
HSS_DATA_DIR=/var/local/hss

HWSUBMISSION_REPO=$HSS_INSTALL_DIR/GIT_CHECKOUT_HWserver
TAGRADING_REPO=$HSS_INSTALL_DIR/GIT_CHECKOUT_TAgrading

PHPUSER=hwphp
CRONGROUP=hwcronphp


########################################################################################################################
########################################################################################################################
# if the top level directory does not exist, then make it
mkdir -p $HSS_INSTALL_DIR


# option for clean install (delete all existing directories/files
if [[ "$#" -eq 1 && $1 == "clean" ]] ; then
    rm -r $HSS_INSTALL_DIR/website
    rm -r $HSS_INSTALL_DIR/src
fi


# set the permissions of the top level directory
chown  root:instructors $HSS_INSTALL_DIR
chmod  771              $HSS_INSTALL_DIR


########################################################################################################################
########################################################################################################################
# RSYNC NOTES
#  a = archive, recurse through directories, preserves file permissions, owner  [ NOT USED, DON'T WANT TO MESS W/ PERMISSIONS ]
#  r = recursive
#  v = verbose, what was actually copied
#  u = only copy things that have changed
#  z = compresses (faster for text, maybe not for binary)
#  (--delete, but probably dont want)
#  / trailing slash, copies contents into target
#  no slash, copies the directory & contents to target


########################################################################################################################
########################################################################################################################
# COPY THE SUBMISSION SERVER WEBSITE (php & javascript)


# copy the website from the repo
rsync -rvuz   $HWSUBMISSION_REPO/public   $HSS_INSTALL_DIR/website



# automatically create the site path file, storing the data directory in the file
echo $HSS_DATA_DIR > $HSS_INSTALL_DIR/website/public/site_path.txt 

# set special user hwphp as owner & group of all website files
find $HSS_INSTALL_DIR/website -exec chown hwphp:hwphp {} \;

# set the permissions of all files
# hwphp can read & execute all directories and read all files
# "other" can cd into all subdirectories
chmod -R 400 $HSS_INSTALL_DIR/website
find $HSS_INSTALL_DIR/website -type d -exec chmod uo+x {} \;
# "other" can read all .txt & .css files
find $HSS_INSTALL_DIR/website -type f -name \*.css -exec chmod o+r {} \;
find $HSS_INSTALL_DIR/website -type f -name \*.txt -exec chmod o+r {} \;
# "other" can read & execute all .js files
find $HSS_INSTALL_DIR/website -type f -name \*.js -exec chmod o+rx {} \;


# create the custom_resources directory
mkdir -p $HSS_INSTALL_DIR/website/public/custom_resources
# instructors will be able to add their own .css file customizations to this directory
find $HSS_INSTALL_DIR/website/public/custom_resources -exec chown root:instructors {} \;
find $HSS_INSTALL_DIR/website/public/custom_resources -exec chmod 775 {} \;


########################################################################################################################
########################################################################################################################
# COPY THE CORE GRADING CODE (C++ files)

# copy the files from the repo
rsync -rvuz $HWSUBMISSION_REPO/grading $HSS_INSTALL_DIR/src
# root will be owner & group of these files
chown -R  root:root $HSS_INSTALL_DIR/src
# "other" can cd into & ls all subdirectories
find $HSS_INSTALL_DIR/src -type d -exec chmod 555 {} \;
# "other" can read all files
find $HSS_INSTALL_DIR/src -type f -exec chmod 444 {} \;





########################################################################################################################
########################################################################################################################
# COPY THE SCRIPTS TO GRADE UPLOADED CODE (bash scripts & untrusted_execute)

# make the directory (has a different name)
mkdir -p $HSS_INSTALL_DIR/bin
# copy all of the files
rsync -rvuz  $HWSUBMISSION_REPO/bin/*   $HSS_INSTALL_DIR/bin/


# most of the scripts should be root only
find $HSS_INSTALL_DIR/bin -type d -exec chown root:instructors {} \;
find $HSS_INSTALL_DIR/bin -type d -exec chmod 551 {} \;
find $HSS_INSTALL_DIR/bin -type f -exec chmod 540 {} \;


# all course builders (instructors & head TAs) need read/execute access to this script
chmod o+rx $HSS_INSTALL_DIR/bin/install_homework_function.sh 


# fix the permissions specifically of the grade_students.sh script
chown root:hwcron $HSS_INSTALL_DIR/bin/grade_students.sh
chmod 750 $HSS_INSTALL_DIR/bin/grade_students.sh


# prepare the untrusted_execute executable with suid

# SUID (Set owner User ID up on execution), allows the hwcron user 
# to run this executable as sudo/root, which is necessary for the 
# "switch user" to untrusted as part of the sandbox.

pushd $HSS_INSTALL_DIR/bin/
# set ownership/permissions on the source code
chown root:root untrusted_execute.c
chmod 500 untrusted_execute.c
# compile the code
g++ -static untrusted_execute.c -o untrusted_execute
# change permissions & set suid: (must be root)
chown root untrusted_execute
chgrp hwcron untrusted_execute
chmod 4550 untrusted_execute
popd
exit


########################################
# COPY THE TA GRADING WEBSITE
# all at once?
#rsync -rvuz $TAGRADING_REPO/ hwphp@localhost:$HSS_INSTALL_DIR/hwgrading_website
# separately?  (is this necessary?)
rsync -rvuz $TAGRADING_REPO/*php hwphp@localhost:$HSS_INSTALL_DIR/hwgrading_website
rsync -rvuz $TAGRADING_REPO/toolbox hwphp@localhost:$HSS_INSTALL_DIR/hwgrading_website
rsync -rvuz $TAGRADING_REPO/lib hwphp@localhost:$HSS_INSTALL_DIR/hwgrading_website
rsync -rvuz $TAGRADING_REPO/account hwphp@localhost:$HSS_INSTALL_DIR/hwgrading_website
rsync -rvuz $TAGRADING_REPO/robots.txt hwphp@localhost:$HSS_INSTALL_DIR/hwgrading_website
rsync -rvuz $TAGRADING_REPO/favicon.ico hwphp@localhost:$HSS_INSTALL_DIR/hwgrading_website


################################################################################################################
################################################################################################################


