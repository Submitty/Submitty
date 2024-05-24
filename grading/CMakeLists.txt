cmake_minimum_required (VERSION 3.20)
project (sample_assignment)

###################################################################################

# these variables will be replaced by INSTALL.sh
set (SUBMITTY_INSTALL_DIR __INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__)
set (SUBMITTY_DATA_DIR __INSTALL__FILLIN__SUBMITTY_DATA_DIR__)

# assuming the core files for config, compilation, running, & grading are here:
set (GRADINGCODE ${SUBMITTY_INSTALL_DIR}/src)
set (JSONCODE ${SUBMITTY_INSTALL_DIR}/vendor/include)

# allowed_autograding_commands files
set (allowed_autograding_commands_default ${SUBMITTY_INSTALL_DIR}/config/allowed_autograding_commands_default.json)
set (allowed_autograding_commands_custom ${SUBMITTY_INSTALL_DIR}/config/allowed_autograding_commands_custom.json)


###################################################################################

# Read in the allowed_autograding_commands json files
file (READ ${allowed_autograding_commands_default} allowed_autograding_commands_default_obj)
file (READ ${allowed_autograding_commands_custom} allowed_autograding_commands_custom_obj)

# Then embed the json files within an allowed_autograding_commands.cpp source file
set (allowed_autograding_commands_headers "#include <string> \n#include <iostream> \n")
set (allowed_autograding_commands_default_definition "extern const std::string GLOBAL_allowed_autograding_commands_default_string = R\"(")
set (allowed_autograding_commands_custom_definition "extern const std::string GLOBAL_allowed_autograding_commands_custom_string = R\"(")
set (end_definition ")\";\n")

file (
  WRITE allowed_autograding_commands.cpp
  "${allowed_autograding_commands_headers}${allowed_autograding_commands_default_definition}${allowed_autograding_commands_default_obj}${end_definition}${allowed_autograding_commands_custom_definition}${allowed_autograding_commands_custom_obj}${end_definition}"
)

###################################################################################
# source files

add_library(submitty_grading STATIC
  ${GRADINGCODE}/grading/allowed_autograding_commands.cpp
  ${GRADINGCODE}/grading/load_config_json.cpp
  ${GRADINGCODE}/grading/TestCase.cpp
  ${GRADINGCODE}/grading/dispatch.cpp
  ${GRADINGCODE}/grading/tokens.cpp
  ${GRADINGCODE}/grading/tokenSearch.cpp
  ${GRADINGCODE}/grading/clean.cpp
  ${GRADINGCODE}/grading/change.cpp
  ${GRADINGCODE}/grading/difference.cpp
  ${GRADINGCODE}/grading/execute.cpp
  ${GRADINGCODE}/grading/execute_limits.cpp
  ${GRADINGCODE}/grading/error_message.cpp
  ${GRADINGCODE}/grading/window_utils.cpp
)

###################################################################################

SET(CMAKE_CXX_FLAGS "${CMAKE_CXX_FLAGS} -pthread")

target_link_libraries(submitty_grading seccomp)

set_property(TARGET submitty_grading APPEND_STRING PROPERTY COMPILE_FLAGS "-g -std=c++11")

include_directories(${GRADINGCODE})
include_directories(${JSONCODE})

###################################################################################
