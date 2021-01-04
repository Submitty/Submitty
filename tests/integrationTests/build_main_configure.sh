#!/bin/bash


which_test_dir=$1
SUBMITTY_INSTALL_DIR=$2

config_directory=${which_test_dir}/assignment_config
GRADINGCODE=${SUBMITTY_INSTALL_DIR}/src/grading

# Use the C Pre-Processor to strip the C & C++ comments from config.json
cpp ${config_directory}/config.json ${config_directory}/complete_config.json
cpp_res=$?
if (( $cpp_res != 0 )); then
    echo -e "\nFailed to run cpp preprocessor"
    exit 1
fi

# Remove cpp markers and run the complete config json through a python json syntax checker.
python3 ${GRADINGCODE}/json_syntax_checker.py ${config_directory}/complete_config.json
py_res=$?
if (( $cpp_res != 0 )); then
    echo -e "\nFailed to load the instructor config.json"
    exit 1
fi

# Create the complete/build config using main_configure
g++ ${GRADINGCODE}/main_configure.cpp ${GRADINGCODE}/load_config_json.cpp ${GRADINGCODE}/execute.cpp \
    ${GRADINGCODE}/TestCase.cpp ${GRADINGCODE}/error_message.cpp ${GRADINGCODE}/window_utils.cpp \
    ${GRADINGCODE}/dispatch.cpp ${GRADINGCODE}/change.cpp ${GRADINGCODE}/difference.cpp \
    ${GRADINGCODE}/tokenSearch.cpp ${GRADINGCODE}/tokens.cpp ${GRADINGCODE}/clean.cpp \
    ${GRADINGCODE}/execute_limits.cpp ${GRADINGCODE}/seccomp_functions.cpp \
    ${GRADINGCODE}/empty_custom_function.cpp -pthread -g -std=c++11 -lseccomp -o configure.out


./configure.out ${config_directory}/complete_config.json ${which_test_dir}/data/build_config.json test_assignment
configure_res=$?

if (( $configure_res != 0 )); then
    echo -e "\nFailed to create a complete_config.json"
    exit 1
fi

# Remove the intermediate config
rm ${config_directory}/complete_config.json

# Copy the build config into the config directory
cp ${which_test_dir}/data/build_config.json ${config_directory}
cp ${which_test_dir}/data/build_config.json ${config_directory}/complete_config.json
cat ${config_directory}/complete_config.json

ls -la .

ls -la ${config_directory}
