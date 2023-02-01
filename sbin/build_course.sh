#!/bin/bash


##########################################################################
# VARIABLES CONFIGURED BY INSTALL.sh
SUBMITTY_INSTALL_DIR=__CREATE_COURSE__FILLIN__SUBMITTY_INSTALL_DIR__
SUBMITTY_DATA_DIR=__CREATE_COURSE__FILLIN__SUBMITTY_DATA_DIR__
SUBMISSION_URL=__CREATE_COURSE__FILLIN__SUBMISSION_URL__

# VARIABLES CONFIGURED BY create_course.sh
term=__CREATE_COURSE__FILLIN__term__
course=__CREATE_COURSE__FILLIN__COURSE__

##########################################################################

# the build_homework function is defined here
. $SUBMITTY_INSTALL_DIR/bin/build_homework_function.sh

# helper variable
MY_COURSE_DIR=$SUBMITTY_DATA_DIR/courses/$term/$course

##########################################################################
# SPECIFIC HOMEWORKS

echo "BUILDING course=$course term=$term... "
date

# generate ASSIGNMENTS.txt
$SUBMITTY_INSTALL_DIR/bin/make_assignments_txt_file.py $MY_COURSE_DIR/config/form $MY_COURSE_DIR/ASSIGNMENTS.txt $@

# pull in the homeworks from an auto-generated file
. $MY_COURSE_DIR/ASSIGNMENTS.txt

echo "--------------------------------------------------------------------------------------"
echo "done building course=$course term=$term"
date

echo -e "course page url: ${SUBMISSION_URL}/index.php?term=${term}&course=${course}"

##########################################################################
