##########################################################################
# HELPER FUNCTION FOR INSTALLING INDIVIDUAL HOMEWORKS
##########################################################################


function build_homework {

    # these variables will be replaced by INSTALL.sh
    SUBMITTY_INSTALL_DIR=__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__
    SUBMITTY_DATA_DIR=__INSTALL__FILLIN__SUBMITTY_DATA_DIR__


    # location of the homework source files, including:
    # $hw_source/config.h
    # $hw_source/provided_code/<instructor code files>
    # $hw_source/test_input/<input files>
    # $hw_source/test_output/<output files>
    # $hw_source/custom_validation_code/<instructor code files>
    hw_source=$1


    # where it should be installed (what semester, course, and assignment number/name)
    semester=$2
    course=$3
    assignment=$4

    course_dir=$SUBMITTY_DATA_DIR/courses/$semester/$course


    # check that the user executing this script is in the course group
    course_group=`stat -c "%G" $course_dir`
    my_username=`id -u -n`
    if groups $my_username | grep -q "\b$course_group\b"; then
        # echo "good:  $my_username is in $course_group"
        : # null statement in bash
    elif [[ "$EUID" -eq 0 ]]; then
        # echo "ok:  script run by root"
        : # null statement in bash
    else
        echo "ERROR!  This script must be run by root, or a member member of the course group."
        echo "        $my_username is NOT in $course_group"
        exit 1
    fi


    hw_build_path=$course_dir/build/$assignment
    hw_bin_path=$course_dir/bin/$assignment
    hw_config=$course_dir/config/build/build_${assignment}.json


    echo "--------------------------------------------------------------------------------------"
    date
    echo "INSTALL assignment config: $hw_source"
    echo "        destination:       $hw_build_path"
    



    # copy the files to the build directory 
    rsync -ruz --delete  $hw_source/   $hw_build_path || ( echo 'ERROR: configuration source directory does not exist or hwcron cannot read this directory' ; exit 1 )
    find $hw_build_path -type d -exec chmod -f ug+rwx,g+s,o= {} \;
    find $hw_build_path -type f -exec chmod -f ug+rw,o= {} \;

    # grab the universal cmake file
    cp $SUBMITTY_INSTALL_DIR/src/grading/Sample_CMakeLists.txt   $hw_build_path/CMakeLists.txt
    chmod -f 660 $hw_build_path/CMakeLists.txt

    # go to the build directory
    pushd $hw_build_path > /dev/null

    # build the configuration, compilation, runner, and validation executables
    # configure cmake, specifying the clang compiler
    CXX=/usr/bin/clang++ cmake . > $hw_build_path/log_cmake_output.txt 2>&1
    cmake_res=$?
    chmod -f 660 $hw_build_path/log_cmake_output.txt
    find $hw_build_path -type d -exec chmod -f ug+rwx,g+s,o= {} \;
    find $hw_build_path -type f -exec chmod -f ug+rw,o= {} \;
    if (( $cmake_res != 0 )); then
        echo -e "\nCMAKE ERROR\nlogfile: $hw_build_path/log_cmake_output.txt\n\n"
        cat $hw_build_path/log_cmake_output.txt
        exit 1
    fi

    # build (in parallel, 8 threads)
    # quit (don't continue on to build other homeworks) if there is a compile error
    make -j 8  > $hw_build_path/log_make_output.txt 2>&1
    # capture exit code of make
    make_res=$?
    chmod -f 660 $hw_build_path/log_make_output.txt
    find $hw_build_path -type d -exec chmod -f ug+rwx,g+s,o= {} \;
    find $hw_build_path -type f -exec chmod -f ug+rw,o= {} \;
    if (( $make_res != 0 )); then
        echo -e "\nMAKE ERROR\nlogfile: $hw_build_path/log_make_output.txt\n\n"
        cat $hw_build_path/log_make_output.txt
        exit 1
    fi

    # set the permissions 
    chmod  -f  u=rw,g=rw,o=r     $hw_config
    chmod  -f  u=rwx,g=rwx,o=x   $hw_bin_path
    chmod  -f  u=rwx,g=rwx,o=x   $hw_bin_path/*out

    # copy the provided code, test input, test output, and custom validation code files to the appropriate directories
    if [ -d $hw_build_path/provided_code/ ]; then
	rsync -ruz --delete $hw_build_path/provided_code/    $course_dir/provided_code/$assignment/
    fi
    if [ -d $hw_build_path/test_input/ ]; then
	rsync -ruz --delete $hw_build_path/test_input/   $course_dir/test_input/$assignment/
    fi
    if [ -d $hw_build_path/test_output/ ]; then
	rsync -ruz --delete $hw_build_path/test_output/  $course_dir/test_output/$assignment/
    fi
    if [ -d $hw_build_path/custom_validation_code/ ]; then
	rsync -ruz --delete $hw_build_path/custom_validation_code/    $course_dir/custom_validation_code/$assignment/
    fi
    
    find $course_dir/provided_code/           -type d -exec chmod -f ug+rwx,g+s,o= {} \;
    find $course_dir/provided_code/           -type f -exec chmod -f ug+rw,o= {} \;
    find $course_dir/test_input/              -type d -exec chmod -f ug+rwx,g+s,o= {} \;
    find $course_dir/test_input/              -type f -exec chmod -f ug+rw,o= {} \;
    find $course_dir/test_output/             -type d -exec chmod -f ug+rwx,g+s,o= {} \;
    find $course_dir/test_output/             -type f -exec chmod -f ug+rw,o= {} \;
    find $course_dir/custom_validation_code/  -type d -exec chmod -f ug+rwx,g+s,o= {} \;
    find $course_dir/custom_validation_code/  -type f -exec chmod -f ug+rw,o= {} \;

    popd > /dev/null
}


##########################################################################
