##########################################################################
# HELPER FUNCTION FOR INSTALLING INDIVIDUAL HOMEWORKS
##########################################################################


function install_homework {


    # DEFAULT INSTALLATION & DATA/CONFIGURATION LOCATIONS
    # (must be changed if you put the files elsewhere)
    HSS_INSTALL_DIR=/usr/local/hss
    HSS_DATA_DIR=/var/local/hss


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

    course_dir=$HSS_DATA_DIR/courses/$semester/$course

    hw_build_path=$course_dir/build/$assignment
    hw_bin_path=$course_dir/bin/$assignment
    hw_config=$course_dir/config/${assignment}_assignment_config.json


    echo "---------------------------------------------------"
    echo "install $hw_source $hw_build_path"
    
    # copy the files to the build directory 
    rsync -rvuz   $hw_source/   $hw_build_path

    # grab the universal cmake file
    cp $HSS_INSTALL_DIR/src/grading/Sample_CMakeLists.txt   $hw_build_path/CMakeLists.txt

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
	rsync -rvuz $hw_build_path/test_input/   $course_dir/test_input/$assignment/
    fi
    if [ -d $hw_build_path/test_output/ ]; then
	rsync -rvuz $hw_build_path/test_output/  $course_dir/test_output/$assignment/
    fi
    if [ -d $hw_build_path/test_code/ ]; then
	rsync -rvuz $hw_build_path/test_code/    $course_dir/test_code/$assignment/
    fi

    popd
    echo "---------------------------------------------------"
}


##########################################################################
