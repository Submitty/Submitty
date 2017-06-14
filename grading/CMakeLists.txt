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
)

###################################################################################

# For now...  Ubuntu 16.04 contains 7 new system calls
set(SPECIFIC_LINUX "__UBUNTU_14_04__")
if (${CMAKE_HOST_SYSTEM} STREQUAL "Linux-4.4.0-75-generic")
  set(SPECIFIC_LINUX "__UBUNTU_16_04__")
endif()

target_link_libraries(submitty_grading seccomp boost_system boost_filesystem)

set_property(TARGET submitty_grading APPEND_STRING PROPERTY COMPILE_FLAGS "-g -std=c++11 -D${SPECIFIC_LINUX}")

include_directories(${GRADINGCODE})

###################################################################################


