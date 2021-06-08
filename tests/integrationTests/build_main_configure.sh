#!/bin/bash


which_test_dir=$1
SUBMITTY_INSTALL_DIR=$2
CONFIGURE_AGENT=$3

config_directory=${which_test_dir}/assignment_config
GRADINGCODE=${SUBMITTY_INSTALL_DIR}/src/grading

# Use the C Pre-Processor to strip the C & C++ comments from config.json
cpp ${config_directory}/config.json ${config_directory}/complete_config.json
cpp_res=$?
if (( $cpp_res != 0 )); then
    echo -e "\nFailed to run cpp preprocessor"
    exit 1
fi

# Run the complete config json through a python json syntax checker.
python3 ${GRADINGCODE}/json_syntax_checker.py ${config_directory}/complete_config.json
py_res=$?
if (( $py_res != 0 )); then
    echo -e "\nFailed to load the instructor config.json"
    exit 1
fi

${CONFIGURE_AGENT} ${config_directory}/complete_config.json ${which_test_dir}/data/build_config.json test_assignment
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

