cmake_minimum_required (VERSION 2.8)
project (sample_assignment)

###################################################################################

# these variables will be replaced by INSTALL.sh
set (SUBMITTY_INSTALL_DIR __INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__)
set (SUBMITTY_DATA_DIR __INSTALL__FILLIN__SUBMITTY_DATA_DIR__)

# assuming the core files for config, compilation, running, & grading are here:
set (GRADINGCODE ${SUBMITTY_INSTALL_DIR}/src)
set (JSONCODE ${SUBMITTY_INSTALL_DIR}/vendor/include)

# allowed command files
set(default_commands_file ${SUBMITTY_INSTALL_DIR}/config/autograding_allowed_commands_default.json)
set(custom_commands_file ${SUBMITTY_INSTALL_DIR}/config/autograding_allowed_commands_custom.json)


###################################################################################

# Read in the allowed commands json files
file(READ ${default_commands_file} default_commands_obj)
file(READ ${custom_commands_file} custom_commands_obj)

# Then embed the json files within a allowed_commands.cpp source file
string(REGEX REPLACE "([\"\\\n])" "\\\\\\1" default_commands_obj "${default_commands_obj}")
set(default_definition "const char *GLOBAL_default_allowed_commands_string = \"")
string(REGEX REPLACE "([\"\\\n])" "\\\\\\1" custom_commands_obj "${custom_commands_obj}")
set(custom_definition "const char *GLOBAL_custom_allowed_commands_string = \"")
set(end_definition "\";\n")

file(WRITE allowed_commands.cpp "${default_definition}${default_commands_obj}${end_definition}${custom_definition}${custom_commands_obj}${end_definition}")


###################################################################################
# source files

add_library(submitty_grading STATIC
  ${GRADINGCODE}/grading/allowed_commands.cpp
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
