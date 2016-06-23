#!/bin/bash


##########################################################################
# VARIABLES CONFIGURED BY INSTALL.sh
SUBMITTY_INSTALL_DIR=__CREATE_COURSE__FILLIN__SUBMITTY_INSTALL_DIR__
SUBMITTY_DATA_DIR=__CREATE_COURSE__FILLIN__SUBMITTY_DATA_DIR__

# VARIABLES CONFIGURED BY create_course.sh
semester=__CREATE_COURSE__FILLIN__SEMESTER__
course=__CREATE_COURSE__FILLIN__COURSE__

##########################################################################

# the build_homework function is defined here
. $SUBMITTY_INSTALL_DIR/bin/build_homework_function.sh

# helper variable
MY_COURSE_DIR=$SUBMITTY_DATA_DIR/courses/$semester/$course

##########################################################################
##########################################################################
# INSTRUCTOR EDITS INFORMATION BELOW
##########################################################################
##########################################################################

# OPTIONAL:  install your .css webpage customizations file
# NOTE: a template file has been placed in your directory

#cp $MY_COUSE_DIR/$semester_$course_main.css $SUBMITTY_INSTALL_DIR/website/public/custom_resources/$semester_$course_main.css
#chmod o+r $SUBMITTY_INSTALL_DIR/website/public/custom_resources/$semester_$course_main.css

##########################################################################
# SPECIFIC HOMEWORKS

echo "BUILDING course=$course semester=$semester... "

# pull in the homeworks from an auto-generated file
. $MY_COURSE_DIR/ASSIGNMENTS.txt

echo "done building course=$course semester=$semester"

##########################################################################
