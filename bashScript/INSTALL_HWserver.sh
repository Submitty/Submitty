#!/bin/bash


########################################################################
#
#    NOTE:  This is a first draft installation script
#
#    It is a copy and may not be fully in sync with latest version
#
########################################################################


# RSYNC NOTES
#  a = archive, recurse through directories, preserves file permissions, owner  [ NOT USED, DON'T WANT TO MESS W/ PERMISSIONS ]
#  r = recursive
#  v = verbose, what was actually copied
#  u = only copy things that have changed
#  z = compresses (faster for text, maybe not for binary)
#  (--delete, but probably dont want)
#  / trailing slash, copies contents into target
#  no slash, copies the directory & contents to target


BASEDIR=/fill/this/in
RCOS_REPO=/fill/this/in
PRIVATE_REPO=/fill/this/in

PHPUSER=hwphp
CRONGROUP=hwcronphp


########################################
# COPY THE SUBMISSION SERVER WEBSITE
rsync -rvuz $RCOS_REPO/public $PHPUSER@localhost:$BASEDIR/website



########################################
# COPY THE WEBSITE CUSTOMIZATIONS FOR THE SPECIFIC COURSES
rsync -vuz XXXXXXXXXXXXXX/fall14/csci1200/template_before_https.php                         $PHPUSER@localhost:$BASEDIR/website/public/view/f14_csci1200_container.php
rsync -vuz XXXXXXXXXXXXXX/fall14/csci1200/f14_csci1200_main.css                             $PHPUSER@localhost:$BASEDIR/website/public/resources/f14_csci1200_main.css
rsync -vuz $RCOS_REPO/Sample_Files/sample_class/f14_csci1200_upload_message.php             $PHPUSER@localhost:$BASEDIR/website/public/view/f14_csci1200_upload_message.php

rsync -vuz XXXXXXXXXXXXXX/fall14/csci1200/template_before_https.php                         $PHPUSER@localhost:$BASEDIR/website/public/view/f14_csci1200test_container.php
rsync -vuz XXXXXXXXXXXXXX/fall14/csci1200/f14_csci1200_main.css                             $PHPUSER@localhost:$BASEDIR/website/public/resources/f14_csci1200test_main.css


rsync -vuz XXXXXXXXXXXXXXX/visualization/F14/template_before_https.php                      $PHPUSER@localhost:$BASEDIR/website/public/view/f14_csci4960_container.php
rsync -vuz XXXXXXXXXXXXXXX/visualization/F14/f14_csci4960_main.css                          $PHPUSER@localhost:$BASEDIR/website/public/resources/f14_csci4960_main.css


########################################
# COPY THE CORE GRADING CODE

rsync -rvuz $RCOS_REPO/grading $BASEDIR/gradingcode
rsync -rvuz $RCOS_REPO/modules $BASEDIR/gradingcode


####
# DISALLOWED & WARNING KEYWORDS FROM SUBMITTED CODE
#rsync -rvuz $PRIVATE_REPO/disallowed_words.txt   $BASEDIR/courses/f14/csci1200/config/disallowed_words.txt
####



########################################
# COPY THE SCRIPT TO GRADE UPLOADED CODE
rsync -vuz  $RCOS_REPO/bashScript/grade_students.sh   $BASEDIR/bin/grade_students.sh
chgrp $CRONGROUP $BASEDIR/bin/grade_students.sh
chmod u+x $BASEDIR/bin/grade_students.sh
chmod g+x $BASEDIR/bin/grade_students.sh



################################################################################################################
################################################################################################################

function install_homework {

    # location of the homework files, including:
    # $hw_source/config.h
    # $hw_source/test_input/<input files>
    # $hw_source/test_output/<output files>
    # $hw_sourre/test_code/<solution/instructor code files>
    hw_source=$1
    # where it should be installed (what semester, course, and assignment number/name)
    semester=$2
    course=$3
    assignment=$4
    hw_code_path=$BASEDIR/courses/$semester/$course/hwconfig/$assignment
    hw_bin_path=$BASEDIR/courses/$semester/$course/bin/$assignment
    hw_config=$BASEDIR/courses/$semester/$course/config/${assignment}_assignment_config.json

    echo "---------------------------------------------------"
    echo "install $hw_source $hw_code_path"
    
    # copy the files
    rsync -rvuz   $hw_source/   $hw_code_path
    # grab the universal cmake file
    cp $RCOS_REPO/Sample_Files/Sample_CMakeLists.txt   $hw_code_path/CMakeLists.txt
    # go to the code directory
    pushd $hw_code_path
    # build the configuration, compilation, runner, and validation executables
    # configure cmake, specifying the clang compiler
    CXX=/usr/bin/clang++ cmake . 
    # build in parallel
    # FIXME: using -j 8 causes fork errors on the server
    make -j 2

    # copy the json config file
    cp $hw_bin_path/assignment_config.json $hw_config
    # set the permissions
    chmod  o+r   $hw_config
    chmod  o+x   $hw_bin_path 
    chmod  o+rx  $hw_bin_path/*out

    # copy the test input, test output, test solution code files to the appropriate directories
    if [ -d $hw_code_path/test_input/ ]; then
	rsync -rvuz $hw_code_path/test_input/   $BASEDIR/courses/$semester/$course/test_input/$assignment/
    fi
    if [ -d $hw_code_path/test_output/ ]; then
	rsync -rvuz $hw_code_path/test_output/  $BASEDIR/courses/$semester/$course/test_output/$assignment/
    fi
    if [ -d $hw_code_path/test_code/ ]; then
	rsync -rvuz $hw_code_path/test_code/    $BASEDIR/courses/$semester/$course/test_code/$assignment/
    fi

    popd
    echo "---------------------------------------------------"
}

################################################################################################################
################################################################################################################





install_homework  $RCOS_REPO/Sample_Files/sample_assignment_config/csci1200_lab01_getting_started/   f14   csci1200   lab1
install_homework  $RCOS_REPO/Sample_Files/sample_assignment_config/csci1200_lab05_memory_debugging/  f14   csci1200   lab5

install_homework  $PRIVATE_REPO/csci1200_hw01_moire_strings/                                f14   csci1200   hw01
install_homework  $PRIVATE_REPO/csci1200_hw02_bowling_classes/                              f14   csci1200   hw02
install_homework  $PRIVATE_REPO/csci1200_hw03_jagged_array/                                 f14   csci1200   hw03
install_homework  $PRIVATE_REPO/csci1200_hw04_preference_lists/                             f14   csci1200   hw04
install_homework  $PRIVATE_REPO/csci1200_hw05_unrolled_linked_lists/                        f14   csci1200   hw05
install_homework  $PRIVATE_REPO/csci1200_hw06_carcassonne_recursion/                        f14   csci1200   hw06
install_homework  $PRIVATE_REPO/csci1200_hw07_library_maps/                                 f14   csci1200   hw07
install_homework  $PRIVATE_REPO/csci1200_hw08_bidirectional_map/                            f14   csci1200   hw08
install_homework  $PRIVATE_REPO/csci1200_hw09_perfect_hashing/                              f14   csci1200   hw09
install_homework  $PRIVATE_REPO/csci1200_hw10_organism_inheritance/                         f14   csci1200   hw10


install_homework  $RCOS_REPO/Sample_Files/sample_assignment_config/csci1100_hw01part1/        f14   csci1200   pythontest
#install_homework  $RCOS_REPO/Sample_Files/sample_assignment_config/csci1100_hw01part2/        f14   csci1100   hw01part2
#install_homework  $RCOS_REPO/Sample_Files/sample_assignment_config/csci1100_hw01part3/        f14   csci1100   hw01part3


install_homework  $RCOS_REPO/Sample_Files/sample_assignment_config/csci4960_any_homework      f14   csci4960   hw01
install_homework  $RCOS_REPO/Sample_Files/sample_assignment_config/csci4960_any_homework      f14   csci4960   hw02
install_homework  $RCOS_REPO/Sample_Files/sample_assignment_config/csci4960_any_homework      f14   csci4960   hw03
install_homework  $RCOS_REPO/Sample_Files/sample_assignment_config/csci4960_any_homework      f14   csci4960   hw04
install_homework  $RCOS_REPO/Sample_Files/sample_assignment_config/csci4960_any_homework      f14   csci4960   hw05
install_homework  $RCOS_REPO/Sample_Files/sample_assignment_config/csci4960_any_homework      f14   csci4960   hw06
install_homework  $RCOS_REPO/Sample_Files/sample_assignment_config/csci4960_any_homework      f14   csci4960   hw07
install_homework  $RCOS_REPO/Sample_Files/sample_assignment_config/csci4960_any_homework      f14   csci4960   hw08
install_homework  $RCOS_REPO/Sample_Files/sample_assignment_config/csci4960_any_homework      f14   csci4960   hw09
install_homework  $RCOS_REPO/Sample_Files/sample_assignment_config/csci4960_any_homework      f14   csci4960   hw10
install_homework  $RCOS_REPO/Sample_Files/sample_assignment_config/csci4960_any_homework      f14   csci4960   hw11
install_homework  $RCOS_REPO/Sample_Files/sample_assignment_config/csci4960_any_homework      f14   csci4960   hw12
install_homework  $RCOS_REPO/Sample_Files/sample_assignment_config/csci4960_any_homework      f14   csci4960   hw13



########################################


 


