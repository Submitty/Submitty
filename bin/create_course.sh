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


#  new_course.sh  <semester>  <course>  <instructor username>  <ta group>

semester=$1
course=$2
instructor=$3
ta_www_group=$4

#base_directory=/projects/submit3
base_directory=/local/scratch0/submit3

echo "semester:     $semester"
echo "course:       $course"
echo "instructor:   $instructor"
echo "ta_www_group: $ta_www_group"

# ta_www_group should contain:  hwphp hwcron instructor ta1 ta2 ta3 ...


#############################################################

if [ ! -d "$base_directory" ]; then
    echo "ERROR: base directory " $base_directory " does not exist"
    exit
fi

if [ ! -d "$base_directory/courses" ]; then
    echo "ERROR: courses directory " $base_directory/courses " does not exist"
    exit
fi

if [ ! -d "$base_directory/courses/$semester" ]; then
    mkdir                   $base_directory/courses/$semester
    chown hwphp:hwphp       $base_directory/courses/$semester
    chmod u=rwx,g=rwx,o=rx  $base_directory/courses/$semester
fi

#############################################################

course_dir=$base_directory/courses/$semester/$course

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


# NOTE: these are the only directories that should be manually edited
#               drwxrws---      instructor   ta_www_group	hwconfig/
#               drwxr-s---      instructor   ta_www_group    config/
create_and_set  u=rwx,g=rxs,o=  $instructor  $ta_www_group	$course_dir/hwconfig
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
#               drwxr-s---      hwphp        ta_www_group    submissions/
#               drwxr-s---      hwcron       ta_www_group    results/
create_and_set  u=rwx,g=rxs,o=  hwphp        $ta_www_group   $course_dir/submissions
create_and_set  u=rwx,g=rxs,o=  hwcron       $ta_www_group   $course_dir/results


# NOTE:    instructor uploads TA HW grade reports & overall grade scores here
#               drwxr-s---      instructor   ta_www_group    reports/
create_and_set  u=rwx,g=rxs,o=  $instructor   $ta_www_group   $course_dir/reports

