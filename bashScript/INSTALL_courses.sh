#!/bin/bash

##########################################################################

# the root of the submission server installation

# change these paths!

BASEDIR=/XXX/XXX/XXX
RCOS_REPO=/XXX/XXX/XXX
PRIVATE_REPO=/XXX/XXX/XXX


# the install_homework function is defined here
. $BASEDIR/bin/install_homework_function.sh


##########################################################################
# SPECIFIC HOMEWORKS FOR SPECIFIC COURSES

# NOTE: When a new homework is added, we also need to edit the
#    BASEDIR/courses/<SEMESTER>/COURSE/config/class.json file


semester=s15
course=csci1200


install_homework  $BASEDIR  $RCOS_REPO/Sample_Files/sample_assignment_config/csci1200_lab01_getting_started/   $semester   $course   lab01
install_homework  $BASEDIR  $PRIVATE_REPO/csci1200_hw01_image_processing/                                      $semester   $course   hw01


semester=s15
course=csci1100

install_homework  $BASEDIR  $RCOS_REPO/Sample_Files/sample_assignment_config/csci1100_hw01part1/               $semester   $course   pythontest


semester=s15
course=csci1100

install_homework  $BASEDIR  $RCOS_REPO/Sample_Files/sample_assignment_config/csci1100_hw01part1/               $semester   $course   pythontest


##########################################################################


 


