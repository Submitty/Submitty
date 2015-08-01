##########################################################################
# HELPER FUNCTION FOR INSTALLING INDIVIDUAL HOMEWORKS
##########################################################################


function install_homework {

    BASEDIR=$1

    # location of the homework files, including:
    # $hw_source/config.h
    # $hw_source/test_input/<input files>
    # $hw_source/test_output/<output files>
    # $hw_sourre/test_code/<solution/instructor code files>

    hw_source=$2
    # where it should be installed (what semester, course, and assignment number/name)
    semester=$3
    course=$4
    assignment=$5
    hw_code_path=$BASEDIR/courses/$semester/$course/build/$assignment
    hw_bin_path=$BASEDIR/courses/$semester/$course/bin/$assignment
    hw_config=$BASEDIR/courses/$semester/$course/config/${assignment}_assignment_config.json

    echo "---------------------------------------------------"
    echo "install $hw_source $hw_code_path"
    
    # copy the files
    rsync -rvuz   $hw_source/   $hw_code_path
    # grab the universal cmake file
#    cp $RCOS_REPO/Sample_Files/Sample_CMakeLists.txt   $hw_code_path/CMakeLists.txt
    cp $BASEDIR/gradingcode/grading/Sample_CMakeLists.txt   $hw_code_path/CMakeLists.txt

    # go to the code directory
    pushd $hw_code_path
    # build the configuration, compilation, runner, and validation executables
    # configure cmake, specifying the clang compiler
    CXX=/usr/bin/clang++ cmake . 
    # build in parallel
    # FIXME: using -j 8 causes fork errors on the server
#    make -j 2
    make 
    
#    # copy the json config file
#    cp $hw_bin_path/assignment_config.json $hw_config
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


##########################################################################
