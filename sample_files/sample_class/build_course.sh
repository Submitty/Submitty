#!/bin/bash


##########################################################################
# VARIABLES CONFIGURED BY INSTALL.sh

HSS_INSTALL_DIR=__INSTALL__FILLIN__HSS_INSTALL_DIR__
HSS_DATA_DIR=__INSTALL__FILLIN__HSS_DATA_DIR__

HSS_REPOSITORY=__INSTALL__FILLIN__HSS_REPOSITORY__
TAGRADING_REPOSITORY=__INSTALL__FILLIN__TAGRADING_REPOSITORY__

HWPHP_USER=__INSTALL__FILLIN__HWPHP_USER__
HWCRON_USER=__INSTALL__FILLIN__HWCRON_USER__
HWCRONPHP_GROUP=__INSTALL__FILLIN__HWCRONPHP_GROUP__
INSTRUCTORS_GROUP=__INSTALL__FILLIN__INSTRUCTORS_GROUP__

# FIXME: Add some error checking to make sure these values were filled in correctly

##########################################################################
# VARIABLES CONFGURED BY create_course.sh

semester=__CREATE_COURSE__FILLIN__SEMESTER__
course=__CREATE_COURSE__FILLIN__COURSE__

# FIXME: Add some error checking to make sure these values were filled in correctly

##########################################################################

# the install_homework function is defined here
. $HSS_INSTALL_DIR/bin/install_homework_function.sh

# helper variable
MY_COURSE_DIR=$HSS_DATA_DIR/courses/$semester/$course

##########################################################################
##########################################################################
# INSTRUCTOR EDITS INFORMATION BELOW
##########################################################################
##########################################################################

# OPTIONAL:  install your .css webpage customizations file
# NOTE: a sample template file is located in $HSS_INSTALL_DIR/sample_files/sample_files/sample_main.css
#cp $MY_COUSE_DIR/$semester_$course_main.css $HSS_INSTALL_DIR/website/public/custom_resources/$semester_$course_main.css

##########################################################################

# RECOMMENDED:  Store your homework configurations in a private repository.
#               Insert location of private repository here.  E.g.: 

#PRIVATE_REPO=$HSS_DATA_DIR/PRIVATE_GIT_CHECKOUT
#PRIVATE_REPO=$HSS_DATA_DIR/courses/$semester/$course/PRIVATE_GIT_CHECKOUT


##########################################################################
# SPECIFIC HOMEWORKS
# NOTE: also need to edit the HSS_DATA_DIR/courses/s15/config/class.json file

echo "BUILDING course=$course semester=$semester... "


chmod o+r $HSS_INSTALL_DIR/website/public/custom_resources/s15_csci1200_main.css 

install_homework   $HSS_INSTALL_DIR/sample_files/sample_assignment_config/csci1200_lab01_getting_started/   $semester   $course   lab01
install_homework   $HSS_INSTALL_DIR/sample_files/sample_assignment_config/csci1100_hw01part1/               $semester   $course   pythontest

#install_homework   $PRIVATE_REPO/csci1200_hw01_image_processing/                                      $semester   $course   hw01
#install_homework   $PRIVATE_REPO/csci1200_hw02_tennis_classes/                                        $semester   $course   hw02

echo "done building course=$course semester=$semester"

##########################################################################
