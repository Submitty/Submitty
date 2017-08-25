cmake_minimum_required (VERSION 2.8)
project (sample_assignment)

###################################################################################

# these variables will be replaced by INSTALL.sh
set (SUBMITTY_INSTALL_DIR __INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__)
set (SUBMITTY_DATA_DIR __INSTALL__FILLIN__SUBMITTY_DATA_DIR__)

# assuming the core files for config, compilation, running, & grading are here:
set (GRADINGCODE ${SUBMITTY_INSTALL_DIR}/src)

###################################################################################
# source files

add_library(submitty_grading
  ${GRADINGCODE}/grading/load_config_json.cpp
  ${GRADINGCODE}/grading/TestCase.cpp 
  ${GRADINGCODE}/grading/JUnitGrader.cpp
  ${GRADINGCODE}/grading/DrMemoryGrader.cpp
  ${GRADINGCODE}/grading/PacmanGrader.cpp
  ${GRADINGCODE}/grading/myersDiff.cpp 
  ${GRADINGCODE}/grading/tokens.cpp 
  ${GRADINGCODE}/grading/tokenSearch.cpp 
  ${GRADINGCODE}/grading/diffNaive.cpp 
  ${GRADINGCODE}/grading/clean.cpp 
  ${GRADINGCODE}/grading/difference.cpp
  ${GRADINGCODE}/grading/execute.cpp 
  ${GRADINGCODE}/grading/execute_limits.cpp 
  ${GRADINGCODE}/grading/error_message.cpp
  ${GRADINGCODE}/grading/window_utils.cpp
)

###################################################################################

target_link_libraries(submitty_grading seccomp boost_system boost_filesystem)

set_property(TARGET submitty_grading APPEND_STRING PROPERTY COMPILE_FLAGS "-g -std=c++11")

include_directories(${GRADINGCODE})

###################################################################################


