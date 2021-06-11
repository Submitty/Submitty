#!/bin/bash

SUBMITTY_INSTALL_DIR=$1
OUTPUT_FILE=$2

GRADINGCODE=${SUBMITTY_INSTALL_DIR}/src/grading

# Create the complete/build config using main_configure
g++ ${GRADINGCODE}/main_configure.cpp ${GRADINGCODE}/load_config_json.cpp ${GRADINGCODE}/execute.cpp \
    ${GRADINGCODE}/TestCase.cpp ${GRADINGCODE}/error_message.cpp ${GRADINGCODE}/window_utils.cpp \
    ${GRADINGCODE}/dispatch.cpp ${GRADINGCODE}/change.cpp ${GRADINGCODE}/difference.cpp \
    ${GRADINGCODE}/tokenSearch.cpp ${GRADINGCODE}/tokens.cpp ${GRADINGCODE}/clean.cpp \
    ${GRADINGCODE}/execute_limits.cpp ${GRADINGCODE}/seccomp_functions.cpp \
    ${GRADINGCODE}/empty_custom_function.cpp -pthread -g -std=c++11 -lseccomp -o ${OUTPUT_FILE}

