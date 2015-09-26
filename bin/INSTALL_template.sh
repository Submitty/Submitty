#!/bin/bash


########################################################################################################################
########################################################################################################################
# this script must be run by root or sudo 
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit
fi

echo -e "\nBeginning installation of the homework submission server\n"


########################################################################################################################
# VARIABLES CONFIGURED BY CONFIGURE.SH
########################################################################################################################

# These variables are specified by running the CONFIGURE.sh script
# (the CONFIGURE.sh script makes a copy of this file and replaces these values)
HSS_INSTALL_DIR=__CONFIGURE__FILLIN__HSS_INSTALL_DIR__
HSS_DATA_DIR=__CONFIGURE__FILLIN__HSS_DATA_DIR__
SVN_PATH=__CONFIGURE__FILLIN__SVN_PATH__

HSS_REPOSITORY=__CONFIGURE__FILLIN__HSS_REPOSITORY__
TAGRADING_REPOSITORY=__CONFIGURE__FILLIN__TAGRADING_REPOSITORY__

HWPHP_USER=__CONFIGURE__FILLIN__HWPHP_USER__
HWCRON_USER=__CONFIGURE__FILLIN__HWCRON_USER__
HWCRONPHP_GROUP=__CONFIGURE__FILLIN__HWCRONPHP_GROUP__
COURSE_BUILDERS_GROUP=__CONFIGURE__FILLIN__COURSE_BUILDERS_GROUP__

UNTRUSTED_UID=__CONFIGURE__FILLIN__UNTRUSTED_UID__
UNTRUSTED_GID=__CONFIGURE__FILLIN__UNTRUSTED_GID__
HWCRON_UID=__CONFIGURE__FILLIN__HWCRON_UID__
HWCRON_GID=__CONFIGURE__FILLIN__HWCRON_GID__
HWPHP_UID=__CONFIGURE__FILLIN__HWPHP_UID__
HWPHP_GID=__CONFIGURE__FILLIN__HWPHP_GID__

DATABASE_HOST=__CONFIGURE__FILLIN__DATABASE_HOST__
DATABASE_USER=__CONFIGURE__FILLIN__DATABASE_USER__
DATABASE_PASSWORD=__CONFIGURE__FILLIN__DATABASE_PASSWORD__

TAGRADING_URL=__CONFIGURE__FILLIN__TAGRADING_URL__
TAGRADING_LOG_PATH=__CONFIGURE__FILLIN__TAGRADING_LOG_PATH__


AUTOGRADING_LOG_PATH=__CONFIGURE__FILLIN__AUTOGRADING_LOG_PATH__



# FIXME: Add some error checking to make sure these values were filled in correctly


#this function takes a single argument, the name of the file to be edited
function replace_fillin_variables {
    sed -i -e "s|__INSTALL__FILLIN__HSS_REPOSITORY__|$HSS_REPOSITORY|g" $1
    sed -i -e "s|__INSTALL__FILLIN__TAGRADING_REPOSITORY__|$TAGRADING_REPOSITORY|g" $1
    sed -i -e "s|__INSTALL__FILLIN__HSS_INSTALL_DIR__|$HSS_INSTALL_DIR|g" $1
    sed -i -e "s|__INSTALL__FILLIN__HSS_DATA_DIR__|$HSS_DATA_DIR|g" $1
    sed -i -e "s|__INSTALL__FILLIN__SVN_PATH__|$SVN_PATH|g" $1
    sed -i -e "s|__INSTALL__FILLIN__HWPHP_USER__|$HWPHP_USER|g" $1
    sed -i -e "s|__INSTALL__FILLIN__HWCRON_USER__|$HWCRON_USER|g" $1
    sed -i -e "s|__INSTALL__FILLIN__HWCRONPHP_GROUP__|$HWCRONPHP_GROUP|g" $1
    sed -i -e "s|__INSTALL__FILLIN__COURSE_BUILDERS_GROUP__|$COURSE_BUILDERS_GROUP|g" $1

    sed -i -e "s|__INSTALL__FILLIN__UNTRUSTED_UID__|$UNTRUSTED_UID|g" $1
    sed -i -e "s|__INSTALL__FILLIN__UNTRUSTED_GID__|$UNTRUSTED_GID|g" $1
    sed -i -e "s|__INSTALL__FILLIN__HWCRON_UID__|$HWCRON_UID|g" $1
    sed -i -e "s|__INSTALL__FILLIN__HWCRON_GID__|$HWCRON_GID|g" $1
    sed -i -e "s|__INSTALL__FILLIN__HWPHP_UID__|$HWPHP_UID|g" $1
    sed -i -e "s|__INSTALL__FILLIN__HWPHP_GID__|$HWPHP_GID|g" $1



    sed -i -e "s|__INSTALL__FILLIN__DATABASE_HOST__|$DATABASE_HOST|g" $1
    sed -i -e "s|__INSTALL__FILLIN__DATABASE_USER__|$DATABASE_USER|g" $1
    sed -i -e "s|__INSTALL__FILLIN__DATABASE_PASSWORD__|$DATABASE_PASSWORD|g" $1

    sed -i -e "s|__INSTALL__FILLIN__TAGRADING_URL__|$TAGRADING_URL|g" $1
    sed -i -e "s|__INSTALL__FILLIN__TAGRADING_LOG_PATH__|$TAGRADING_LOG_PATH|g" $1

    sed -i -e "s|__INSTALL__FILLIN__AUTOGRADING_LOG_PATH__|$AUTOGRADING_LOG_PATH|g" $1


    # FIXME: Add some error checking to make sure these values were filled in correctly
}


########################################################################################################################
########################################################################################################################
# if the top level INSTALL directory does not exist, then make it
mkdir -p $HSS_INSTALL_DIR


# option for clean install (delete all existing directories/files
if [[ "$#" -eq 1 && $1 == "clean" ]] ; then

    echo -e "\nDeleting directories for a clean installation\n"

    rm -r $HSS_INSTALL_DIR/website
    rm -r $HSS_INSTALL_DIR/hwgrading_website
    rm -r $HSS_INSTALL_DIR/src
    rm -r $HSS_INSTALL_DIR/bin
fi


# set the permissions of the top level directory
chown  root:$COURSE_BUILDERS_GROUP  $HSS_INSTALL_DIR
chmod  751                          $HSS_INSTALL_DIR


########################################################################################################################
########################################################################################################################
# if the top level DATA, COURSES, & LOGS directores do not exist, then make them

echo -e "Make top level directores & set permissions"

mkdir -p $HSS_DATA_DIR
mkdir -p $HSS_DATA_DIR/courses
mkdir -p $HSS_DATA_DIR/tagrading_logs
mkdir -p $HSS_DATA_DIR/autograding_logs

# set the permissions of these directories
chown  root:$COURSE_BUILDERS_GROUP   $HSS_DATA_DIR
chmod  751                           $HSS_DATA_DIR
chown  root:$COURSE_BUILDERS_GROUP   $HSS_DATA_DIR/courses
chmod  751                           $HSS_DATA_DIR/courses
chown  hwphp:$COURSE_BUILDERS_GROUP  $HSS_DATA_DIR/tagrading_logs
chmod  u+rwx,g+rxs                   $HSS_DATA_DIR/tagrading_logs
chown  hwcron:$COURSE_BUILDERS_GROUP $HSS_DATA_DIR/autograding_logs
chmod  u+rwx,g+rxs                   $HSS_DATA_DIR/autograding_logs

# if the to_be_graded directories do not exist, then make them
mkdir -p $HSS_DATA_DIR/to_be_graded_interactive
mkdir -p $HSS_DATA_DIR/to_be_graded_batch

# set the permissions of these directories

#hwphp will write items to this list, hwcron will remove them
chown  $HWCRON_USER:$HWCRONPHP_GROUP        $HSS_DATA_DIR/to_be_graded_interactive
chmod  770                                  $HSS_DATA_DIR/to_be_graded_interactive
#course builders (instructors & head TAs) will write items to this todo list, hwcron will remove them
chown  $HWCRON_USER:$COURSE_BUILDERS_GROUP  $HSS_DATA_DIR/to_be_graded_batch
chmod  770                                  $HSS_DATA_DIR/to_be_graded_batch




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

echo -e "Copy the submission website"

# copy the website from the repo
rsync -ruz   $HSS_REPOSITORY/public   $HSS_INSTALL_DIR/website

# automatically create the site path file, storing the data directory in the file
echo $HSS_DATA_DIR > $HSS_INSTALL_DIR/website/public/site_path.txt 

# set special user $HWPHP_USER as owner & group of all website files
find $HSS_INSTALL_DIR/website -exec chown $HWPHP_USER:$HWPHP_USER {} \;

# set the permissions of all files
# $HWPHP_USER can read & execute all directories and read all files
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
# course builders will be able to add their own .css file customizations to this directory
find $HSS_INSTALL_DIR/website/public/custom_resources -exec chown root:$COURSE_BUILDERS_GROUP {} \;
find $HSS_INSTALL_DIR/website/public/custom_resources -exec chmod 775 {} \;


########################################################################################################################
########################################################################################################################
# COPY THE CORE GRADING CODE (C++ files)

echo -e "Copy the grading code"

# copy the files from the repo
rsync -ruz $HSS_REPOSITORY/grading $HSS_INSTALL_DIR/src
# root will be owner & group of these files
chown -R  root:root $HSS_INSTALL_DIR/src
# "other" can cd into & ls all subdirectories
find $HSS_INSTALL_DIR/src -type d -exec chmod 555 {} \;
# "other" can read all files
find $HSS_INSTALL_DIR/src -type f -exec chmod 444 {} \;


#replace necessary variables
replace_fillin_variables $HSS_INSTALL_DIR/src/grading/Sample_CMakeLists.txt


########################################################################################################################
########################################################################################################################
# COPY THE SAMPLE FILES FOR COURSE MANAGEMENT

echo -e "Copy the sample files"

# copy the files from the repo
rsync -ruz $HSS_REPOSITORY/sample_files $HSS_INSTALL_DIR

# root will be owner & group of these files
chown -R  root:root $HSS_INSTALL_DIR/sample_files
# but everyone can read all that files & directories, and cd into all the directories
find $HSS_INSTALL_DIR/sample_files -type d -exec chmod 555 {} \;
find $HSS_INSTALL_DIR/sample_files -type f -exec chmod 444 {} \;


########################################################################################################################
########################################################################################################################
# BUILD JUNIT TEST RUNNER (.java file)

echo -e "Build the junit test runner"

# copy the file from the repo
rsync -ruz $HSS_REPOSITORY/junit_test_runner/TestRunner.java $HSS_INSTALL_DIR/JUnit/TestRunner.java

pushd $HSS_INSTALL_DIR/JUnit > /dev/null
# root will be owner & group of the source file
chown  root:root  TestRunner.java
# everyone can read this file
chmod  444 TestRunner.java

# compile the executable
javac -cp ./junit-4.12.jar TestRunner.java

# everyone can read the compiled file
chown root:root TestRunner.class
chmod 444 TestRunner.class

popd > /dev/null

########################################################################################################################
########################################################################################################################
# COPY THE SCRIPTS TO GRADE UPLOADED CODE (bash scripts & untrusted_execute)

echo -e "Copy the scripts"

# make the directory (has a different name)
mkdir -p $HSS_INSTALL_DIR/bin
chown root:$COURSE_BUILDERS_GROUP $HSS_INSTALL_DIR/bin
chmod 751 $HSS_INSTALL_DIR/bin

# copy all of the files
rsync -ruz  $HSS_REPOSITORY/bin/*   $HSS_INSTALL_DIR/bin/
#replace necessary variables in the copied scripts
replace_fillin_variables $HSS_INSTALL_DIR/bin/create_course.sh
replace_fillin_variables $HSS_INSTALL_DIR/bin/grade_students.sh
replace_fillin_variables $HSS_INSTALL_DIR/bin/grading_done.sh
replace_fillin_variables $HSS_INSTALL_DIR/bin/regrade.sh
replace_fillin_variables $HSS_INSTALL_DIR/bin/build_course.sh
replace_fillin_variables $HSS_INSTALL_DIR/bin/build_homework_function.sh
replace_fillin_variables $HSS_INSTALL_DIR/bin/fake_submit_button_press.sh
replace_fillin_variables $HSS_INSTALL_DIR/bin/untrusted_execute.c

# most of the scripts should be root only
find $HSS_INSTALL_DIR/bin -type f -exec chown root:root {} \;
find $HSS_INSTALL_DIR/bin -type f -exec chmod 500 {} \;

# all course builders (instructors & head TAs) need read/execute access to these scripts
chown root:$COURSE_BUILDERS_GROUP $HSS_INSTALL_DIR/bin/build_homework_function.sh 
chown root:$COURSE_BUILDERS_GROUP $HSS_INSTALL_DIR/bin/regrade.sh
chown root:$COURSE_BUILDERS_GROUP $HSS_INSTALL_DIR/bin/grading_done.sh
chmod 550 $HSS_INSTALL_DIR/bin/build_homework_function.sh 
chmod 550 $HSS_INSTALL_DIR/bin/regrade.sh
chmod 550 $HSS_INSTALL_DIR/bin/grading_done.sh

# fix the permissions specifically of the grade_students.sh script
chown root:$HWCRON_USER $HSS_INSTALL_DIR/bin/grade_students.sh
chmod 550 $HSS_INSTALL_DIR/bin/grade_students.sh


# prepare the untrusted_execute executable with suid

# SUID (Set owner User ID up on execution), allows the $HWCRON_USER 
# to run this executable as sudo/root, which is necessary for the 
# "switch user" to untrusted as part of the sandbox.

pushd $HSS_INSTALL_DIR/bin/ > /dev/null
# set ownership/permissions on the source code
chown root:root untrusted_execute.c
chmod 500 untrusted_execute.c
# compile the code
g++ -static untrusted_execute.c -o untrusted_execute
# change permissions & set suid: (must be root)
chown root untrusted_execute
chgrp $HWCRON_USER untrusted_execute
chmod 4550 untrusted_execute
popd > /dev/null


################################################################################################################
################################################################################################################
# COPY THE TA GRADING WEBSITE

echo -e "Copy the ta grading website"

rsync  -ruz $TAGRADING_REPOSITORY/*php         $HSS_INSTALL_DIR/hwgrading_website
rsync  -ruz $TAGRADING_REPOSITORY/toolbox      $HSS_INSTALL_DIR/hwgrading_website
rsync  -ruz $TAGRADING_REPOSITORY/lib          $HSS_INSTALL_DIR/hwgrading_website
rsync  -ruz $TAGRADING_REPOSITORY/account      $HSS_INSTALL_DIR/hwgrading_website
rsync  -ruz $TAGRADING_REPOSITORY/app          $HSS_INSTALL_DIR/hwgrading_website
    
# set special user $HWPHP_USER as owner & group of all hwgrading_website files
find $HSS_INSTALL_DIR/hwgrading_website -exec chown $HWPHP_USER:$HWPHP_USER {} \;

# set the permissions of all files
# $HWPHP_USER can read & execute all directories and read all files
# "other" can cd into all subdirectories
chmod -R 400 $HSS_INSTALL_DIR/hwgrading_website
find $HSS_INSTALL_DIR/hwgrading_website -type d -exec chmod uo+x {} \;
# "other" can read all .txt & .css files
find $HSS_INSTALL_DIR/hwgrading_website -type f -name \*.css -exec chmod o+r {} \;
find $HSS_INSTALL_DIR/hwgrading_website -type f -name \*.txt -exec chmod o+r {} \;
find $HSS_INSTALL_DIR/hwgrading_website -type f -name \*.ico -exec chmod o+r {} \;
find $HSS_INSTALL_DIR/hwgrading_website -type f -name \*.css -exec chmod o+r {} \;
find $HSS_INSTALL_DIR/hwgrading_website -type f -name \*.png -exec chmod o+r {} \;
find $HSS_INSTALL_DIR/hwgrading_website -type f -name \*.jpg -exec chmod o+r {} \;
find $HSS_INSTALL_DIR/hwgrading_website -type f -name \*.gif -exec chmod o+r {} \;

# "other" can read & execute all .js files
find $HSS_INSTALL_DIR/hwgrading_website -type f -name \*.js -exec chmod o+rx {} \;


replace_fillin_variables $HSS_INSTALL_DIR/hwgrading_website/toolbox/configs/master.php


################################################################################################################
################################################################################################################


echo -e "\nCompleted installation of the homework submission server\n"
