#!/bin/bash



########################################################################################################################
# this script will fake hitting the "submit" button for a collection
# of homeworks for a collection of users.  

# NOTE: This only makes sense for SVN configured assignments


########################################################################################################################
# USAGE

# single assignment
#  sudo -u hwphp ./fake_submit_button_press.sh  SEMESTER  COURSE   USERNAME  ASSIGNMENT_ID


# looping over a bunch of homeworks and a bunch of users
#  for hw in hw00 hw04 hw05; do for usr in foo bar; do sudo -u hwphp ./fake_submit_button_press.sh s15 csci2600 $usr $hw; done; done 


########################################################################################################################
# these variables will be replaced by INSTALL.sh

SUBMITTY_INSTALL_DIR=__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__
SUBMITTY_DATA_DIR=__INSTALL__FILLIN__SUBMITTY_DATA_DIR__

HWPHP_USER=__INSTALL__FILLIN__HWPHP_USER__
HWCRON_USER=__INSTALL__FILLIN__HWCRON_USER__

HWPHP_UID=__INSTALL__FILLIN__HWPHP_UID__

########################################################################################################################
########################################################################################################################
# this script must be run by the hwphp user
if [[ "$UID" -ne "$HWPHP_UID" ]] ; then
    echo "ERROR: This script must be run by $HWPHP_USER"
    exit
fi

########################################################################################################################
if [[ $# -ne "4" ]] ; then
    echo "ERROR: Usage, wrong number of arguments"
    echo "   sudo ./fake_submit_button_press.sh  <semester>  <course>  <username>  <homework id>"
    exit
fi

semester=$1
course=$2
username=$3
homework=$4


# some error checking
submissions_dir=$SUBMITTY_DATA_DIR/courses/$semester/$course/submissions

config_file=$SUBMITTY_DATA_DIR/courses/$semester/$course/config/build/build_${homework}.json

if [ ! -d "$submissions_dir" ]; then
    echo -e "ERROR: specific course submissions " $course_dir " does not exist!"
    exit
fi


if [ ! -f "$config_file" ]; then
    echo -e "ERROR: specific homework configuration file " $config_file " does not exist!"
    exit
fi


########################################################################################################################

# make the assignment directory (if needed)
mkdir -p $submissions_dir/$homework
chmod o-rwx $submissions_dir/$homework

# make the user directory (if needed)
mkdir -p $submissions_dir/$homework/$username
chmod o-rwx $submissions_dir/$homework/$username

# determine the next version number...
next="1"
while [ -d $submissions_dir/$homework/$username/$next ]
do
    ((next++))
done


echo "creating submission: $submissions_dir/$homework/$username/$next"
timestamp=`date +"%Y-%m-%d %T"`
echo "timestamp is $timestamp"


# make the directory
mkdir -p $submissions_dir/$homework/$username/$next
chmod o-rwx $submissions_dir/$homework/$username/$next

# create & write the timestamp file
echo $timestamp > $submissions_dir/$homework/$username/$next/.submit.timestamp

# create & write the timestamp file
touch $submissions_dir/$homework/$username/$next/.submit.SVN_CHECKOUT

# create/update the active version file
echo "{\"active_assignment\":$next}" > $submissions_dir/$homework/$username/user_assignment_settings.json

########################################################################################################################
