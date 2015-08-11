#!/bin/bash


########################################################################################################################
########################################################################################################################
# this script must be run by root or sudo 
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit
fi

########################################################################################################################
########################################################################################################################

#!/bin/bash

if [[ $# -ne "4" ]] ; then
    echo "ERROR: Usage, wrong number of arguments"
    echo "   create_course.sh  <semester>  <course>  <instructor username>  <ta group>"
    exit
fi

semester=$1
course=$2
instructor=$3
ta_www_group=$4

#FIXME: check the validity of these arguments


HSS_INSTALL_DIR=__INSTALL__FILLIN__HSS_INSTALL_DIR__
HSS_DATA_DIR=__INSTALL__FILLIN__HSS_DATA_DIR__

HWPHP_USER=__INSTALL__FILLIN__HWPHP_USER__
HWCRON_USER=__INSTALL__FILLIN__HWCRON_USER__



#this function takes a single argument, the name of the file to be edited
function replace_fillin_variables {
#    sed -i -e "s|__CREATE_COURSE__FILLIN__HSS_REPOSITORY__|$HSS_REPOSITORY|g" $1
#    sed -i -e "s|__CREATE_COURSE__FILLIN__TAGRADING_REPOSITORY__|$TAGRADING_REPOSITORY|g" $1
    sed -i -e "s|__CREATE_COURSE__FILLIN__HSS_INSTALL_DIR__|$HSS_INSTALL_DIR|g" $1
    sed -i -e "s|__CREATE_COURSE__FILLIN__HSS_DATA_DIR__|$HSS_DATA_DIR|g" $1
    sed -i -e "s|__CREATE_COURSE__FILLIN__HWPHP_USER__|$HWPHP_USER|g" $1
    sed -i -e "s|__CREATE_COURSE__FILLIN__HWCRON_USER__|$HWCRON_USER|g" $1
#    sed -i -e "s|__CREATE_COURSE__FILLIN__HWCRONPHP_GROUP__|$HWCRONPHP_GROUP|g" $1
#    sed -i -e "s|__CREATE_COURSE__FILLIN__INSTRUCTORS_GROUP__|$INSTRUCTORS_GROUP|g" $1
    # FIXME: Add some error checking to make sure these values were filled in correctly
}


echo "semester:     $semester"
echo "course:       $course"
echo "instructor:   $instructor"
echo "ta_www_group: $ta_www_group"

# ta_www_group should contain:  $HWPHP_USER  $HWCRON_USER instructor ta1 ta2 ta3 ...

#############################################################

if [ ! -d "$HSS_DATA_DIR" ]; then
    echo "ERROR: base directory " $HSS_DATA_DIR " does not exist"
    exit
fi

if [ ! -d "$HSS_DATA_DIR/courses" ]; then
    echo "ERROR: courses directory " $HSS_DATA_DIR/courses " does not exist"
    exit
fi

if [ ! -d "$HSS_DATA_DIR/courses/$semester" ]; then
    mkdir                            $HSS_DATA_DIR/courses/$semester
    chown $HWPHP_USER:$HWPHP_USER    $HSS_DATA_DIR/courses/$semester
    chmod u=rwx,g=rwx,o=rx           $HSS_DATA_DIR/courses/$semester
fi

#############################################################

course_dir=$HSS_DATA_DIR/courses/$semester/$course

if [ -d "$course_dir" ]; then
    echo "ERROR: specific course directory " $course_dir " already exists"
    exit
fi



function create_and_set {
    permissions="$1"
    owner="$2"
    group="$3"
    directory="$4"
    mkdir                 $directory
    chown $owner:$group   $directory
    chmod $permissions    $directory
}


#               drwxrws---      instructor   ta_www_group    ./
create_and_set  u=rwx,g=rxs,o=  $instructor  $ta_www_group   $course_dir


#               drwxrws---      instructor   ta_www_group    build/
#               drwxr-s---      instructor   ta_www_group    config/
create_and_set  u=rwx,g=rxs,o=  $instructor  $ta_www_group   $course_dir/build
create_and_set  u=rwx,g=rxs,o=  $instructor  $ta_www_group   $course_dir/config


# NOTE: when homework is    installed, grading executables, code, & datafiles are placed here
#               drwxr-s---      instructor   ta_www_group    bin/
#               drwxr-s---      instructor   ta_www_group    test_code/
#               drwxr-s---      instructor   ta_www_group    test_input/
#               drwxr-s---      instructor   ta_www_group    test_output/
create_and_set  u=rwx,g=rxs,o=  $instructor  $ta_www_group   $course_dir/bin
create_and_set  u=rwx,g=rxs,o=  $instructor  $ta_www_group   $course_dir/test_code
create_and_set  u=rwx,g=rxs,o=  $instructor  $ta_www_group   $course_dir/test_input
create_and_set  u=rwx,g=rxs,o=  $instructor  $ta_www_group   $course_dir/test_output


# NOTE: on each student submission, files are written to these directories
#               drwxr-s---      $HWPHP_USER        ta_www_group    submissions/
#               drwxr-s---      $HWCRON_USER       ta_www_group    results/
#               drwxr-s---      $HWCRON_USER       ta_www_group    checkout/
create_and_set  u=rwx,g=rxs,o=  $HWPHP_USER        $ta_www_group   $course_dir/submissions
create_and_set  u=rwx,g=rxs,o=  $HWCRON_USER       $ta_www_group   $course_dir/results
create_and_set  u=rwx,g=rxs,o=  $HWCRON_USER       $ta_www_group   $course_dir/checkout


# NOTE:    instructor uploads TA HW grade reports & overall grade scores here
#               drwxr-s---      instructor   ta_www_group    reports/
create_and_set  u=rwx,g=rxs,o=  $instructor   $ta_www_group   $course_dir/reports


#############################################################

# copy the build_course.sh script
cp $HSS_INSTALL_DIR/sample_files/sample_class/build_course.sh $course_dir/BUILD_${course}.sh
chown instructor:ta_www_group $course_dir/BUILD_${course}.sh
chmod 740 $course_dir/BUILD_${course}.sh
replace_fillin_variables $course_dir/BUILD_${course}.sh

# copy the sample_main.css file for webpage customizations
cp $HSS_INSTALL_DIR/sample_files/sample_class/sample_main.css $course_dir/${semester}_${course}_main.css
chown instructor:ta_www_group $course_dir/${semester}_${course}_main.css
chmod 640 $course_dir/${semester}_${course}_main.css
replace_fillin_variables $course_dir/${semester}_${course}_main.css

#############################################################
