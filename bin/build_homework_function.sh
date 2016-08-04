##########################################################################
# HELPER FUNCTION FOR INSTALLING INDIVIDUAL HOMEWORKS
##########################################################################


function build_homework {

    # these variables will be replaced by INSTALL.sh
    SUBMITTY_INSTALL_DIR=__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__
    SUBMITTY_DATA_DIR=__INSTALL__FILLIN__SUBMITTY_DATA_DIR__


    # location of the homework source files, including:
    # $hw_source/config.h
    # $hw_source/test_input/<input files>
    # $hw_source/test_output/<output files>
    # $hw_sourre/test_code/<solution/instructor code files>
    hw_source=$1


    # where it should be installed (what semester, course, and assignment number/name)
    semester=$2
    course=$3
    assignment=$4

    course_dir=$SUBMITTY_DATA_DIR/courses/$semester/$course

    hw_build_path=$course_dir/build/$assignment
    hw_bin_path=$course_dir/bin/$assignment
    hw_config=$course_dir/config/build/build_${assignment}.json


    echo "---------------------------------------------------"
    echo "install $hw_source $hw_build_path"
    
    # copy the files to the build directory 
    rsync -rvuz --delete  $hw_source/   $hw_build_path
    find $hw_build_path -type d -exec chmod 770 {} \;
    find $hw_build_path -type d -exec chmod g+s {} \;
    find $hw_build_path -type f -exec chmod 660 {} \;

    # grab the universal cmake file
    cp $SUBMITTY_INSTALL_DIR/src/grading/Sample_CMakeLists.txt   $hw_build_path/CMakeLists.txt
    chmod 660 $hw_build_path/CMakeLists.txt

    # go to the build directory
    pushd $hw_build_path

    # build the configuration, compilation, runner, and validation executables
    # configure cmake, specifying the clang compiler
    CXX=/usr/bin/clang++ cmake . 

    # build (in parallel, 8 threads)
    # quit (don't continue on to build other homeworks) if there is a compile error
    make -j 8 || exit
    
    # set the permissions 
    chmod  o+r   $hw_config
    chmod  o+x   $hw_bin_path 
    chmod  o+rx  $hw_bin_path/*out

    # copy the test input, test output, test solution code files to the appropriate directories
    if [ -d $hw_build_path/test_input/ ]; then
	rsync -rvuz --delete $hw_build_path/test_input/   $course_dir/test_input/$assignment/
    fi
    if [ -d $hw_build_path/test_output/ ]; then
	rsync -rvuz --delete $hw_build_path/test_output/  $course_dir/test_output/$assignment/
    fi
    if [ -d $hw_build_path/test_code/ ]; then
	rsync -rvuz --delete $hw_build_path/test_code/    $course_dir/test_code/$assignment/
    fi

    popd
    echo "---------------------------------------------------"
}


##########################################################################
