#!/bin/bash


##########################################################################
# VARIABLES CONFIGURED BY INSTALL.sh
HSS_INSTALL_DIR=__INSTALL__FILLIN__HSS_INSTALL_DIR__
HSS_DATA_DIR=__INSTALL__FILLIN__HSS_DATA_DIR__

# VARIABLES CONFIGURED BY create_course.sh
semester=__CREATE_COURSE__FILLIN__SEMESTER__
course=__CREATE_COURSE__FILLIN__COURSE__

##########################################################################

# the build_homework function is defined here
. $HSS_INSTALL_DIR/bin/build_homework_function.sh

# helper variable
MY_COURSE_DIR=$HSS_DATA_DIR/courses/$semester/$course

##########################################################################
##########################################################################
# INSTRUCTOR EDITS INFORMATION BELOW
##########################################################################
##########################################################################

# OPTIONAL:  install your .css webpage customizations file
# NOTE: a template file has been placed in your directory

#cp $MY_COUSE_DIR/$semester_$course_main.css $HSS_INSTALL_DIR/website/public/custom_resources/$semester_$course_main.css
#chmod o+r $HSS_INSTALL_DIR/website/public/custom_resources/$semester_$course_main.css

##########################################################################

# RECOMMENDED:  Store your homework configurations in a private repository.
#               Insert location of private repository here.  E.g.: 

#PRIVATE_REPO=$HSS_DATA_DIR/PRIVATE_GIT_CHECKOUT
# or
#PRIVATE_REPO=$HSS_DATA_DIR/courses/$semester/$course/PRIVATE_GIT_CHECKOUT


##########################################################################
# SPECIFIC HOMEWORKS
# NOTE: also need to edit the HSS_DATA_DIR/courses/$semester/$course/config/class.json file

echo "BUILDING course=$course semester=$semester... "

# build a few sample homeworks from the public repo
build_homework   $HSS_INSTALL_DIR/sample_files/sample_assignment_config/python_simple_homework/            $semester   $course   python_hw01
build_homework   $HSS_INSTALL_DIR/sample_files/sample_assignment_config/python_buggy_output/               $semester   $course   python_hw02

build_homework   $HSS_INSTALL_DIR/sample_files/sample_assignment_config/cpp_simple_lab/                    $semester   $course   cpp_lab01
build_homework   $HSS_INSTALL_DIR/sample_files/sample_assignment_config/cpp_memory_debugging_lab/          $semester   $course   cpp_lab02

# build homeworks from a private repo
#build_homework   $PRIVATE_REPO/csci1200_hw01_image_processing/                                      $semester   $course   hw01
#build_homework   $PRIVATE_REPO/csci1200_hw02_tennis_classes/                                        $semester   $course   hw02

echo "done building course=$course semester=$semester"

##########################################################################
