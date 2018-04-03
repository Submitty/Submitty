#include <sys/types.h>
#include <sys/stat.h>
#include <cstdio>
#include <cstddef>
#include <cstdlib>
#include <unistd.h>
#include <fcntl.h>
#include <elf.h>
#include <algorithm>
#include <cassert>

// COMPILATION NOTE: Must pass -lseccomp to build
#include <seccomp.h>
#include <set>
#include <string>
#include <seccomp.h>
#include <iostream>
#include <fstream>
#include <vector>
#include <string>

#define SUBMITTY_INSTALL_DIRECTORY  std::string("__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__")

#define ALLOW_SYSCALL(name)  allow_syscall(sc,SCMP_SYS(name),#name)

inline void allow_syscall(scmp_filter_ctx sc, int syscall, const std::string &syscall_string) {
  //std::cout << "allow " << syscall_string << std::endl;
  int res = seccomp_rule_add(sc, SCMP_ACT_ALLOW, syscall, 0);
  if (res < 0) {
    std::cerr << "WARNING:  Errno " << res << " installing seccomp rule for " << syscall_string << std::endl;
  }
}

// ===========================================================================
// ===========================================================================
//
// This helper file defines one function:
// void allow_system_calls(scmp_filter_ctx sc, const std::set<std::string> &categories) {
//
// It is placed in a separate file, since the helper utility
// system_call_check.cpp parses this function to define the categories.
//
#include "system_call_categories.cpp"
#include "json.hpp"

// ===========================================================================
// ===========================================================================

int install_syscall_filter(bool is_32, const std::string &my_program, std::ofstream &execute_logfile, const nlohmann::json &whole_config) {

  int res;
  scmp_filter_ctx sc = seccomp_init(SCMP_ACT_KILL);
  int target_arch = is_32 ? SCMP_ARCH_X86 : SCMP_ARCH_X86_64;
  if (seccomp_arch_native() != target_arch) {
    res = seccomp_arch_add(sc, target_arch);
    if (res != 0) {
      //fprintf(stderr, "seccomp_arch_add failed: %d\n", res);
      return 1;
    }
  }

  // libseccomp uses pseudo-syscalls to let us use the 64-bit split
  // system call names for SYS_socketcall on 32-bit.  The translation
  // being on their side means we have no choice in the matter as we
  // cannot pass them the number for the target: only for the source.
  // We could use raw seccomp-bpf instead.

  std::set<std::string> categories;
  
  // grep ' :' grading/system_call_categories.cpp | grep WHITELIST | cut -f 6 -d ' '
  // grep ' :' grading/system_call_categories.cpp | grep RESTRICTED | cut -f 6 -d ' '
  // grep ' :' grading/system_call_categories.cpp | grep FORBIDDEN | cut -f 6 -d ' ' 

  std::set<std::string> whitelist_categories = {
    "PROCESS_CONTROL",
    "PROCESS_CONTROL_MEMORY",
    "PROCESS_CONTROL_WAITING",
    "FILE_MANAGEMENT",
    "DEVICE_MANAGEMENT",
    "INFORMATION_MAINTENANCE"
  };

  std::set<std::string> restricted_categories = {
    "PROCESS_CONTROL_MEMORY_ADVANCED",
    "PROCESS_CONTROL_NEW_PROCESS_THREAD",
    "PROCESS_CONTROL_SYNCHRONIZATION",
    "PROCESS_CONTROL_SCHEDULING",
    "PROCESS_CONTROL_ADVANCED",
    "PROCESS_CONTROL_GET_SET_USER_GROUP_ID",
    "FILE_MANAGEMENT_MOVE_DELETE_RENAME_FILE_DIRECTORY",
    "FILE_MANAGEMENT_PERMISSIONS",
    "FILE_MANAGEMENT_CAPABILITIES",
    "FILE_MANAGEMENT_EXTENDED_ATTRIBUTES",
    "FILE_MANAGEMENT_RARE",
    "DEVICE_MANAGEMENT_ADVANCED",
    "DEVICE_MANAGEMENT_NEW_DEVICE",
    "INFORMATION_MAINTENANCE_ADVANCED",
    "COMMUNICATIONS_AND_NETWORKING_SOCKETS_MINIMAL",
    "COMMUNICATIONS_AND_NETWORKING_SOCKETS",
    "COMMUNICATIONS_AND_NETWORKING_SIGNALS",
    "COMMUNICATIONS_AND_NETWORKING_INTERPROCESS_COMMUNICATION",
    "TGKILL",
    "COMMUNICATIONS_AND_NETWORKING_KILL",
    "UNKNOWN",
    "UNKNOWN_MODULE",
    "UNKNOWN_REMAP_PAGES"
  };

  std::set<std::string> forbidden_categories = {
    "INFORMATION_MAINTENANCE_SET_TIME"
  };


  // ---------------------------------------------------------------
  // READ ALLOWED SYSTEM CALLS FROM CONFIG.JSON
  const nlohmann::json &config_whitelist = whole_config.value("allow_system_calls",nlohmann::json());
  for (nlohmann::json::const_iterator cwitr = config_whitelist.begin();
       cwitr != config_whitelist.end(); cwitr++) {
    std::string my_category = *cwitr;
    if (my_category.size() > 27 && my_category.substr(0,27) == "ALLOW_SYSTEM_CALL_CATEGORY_") {
      my_category = my_category.substr(27,my_category.size()-27);
    }
    // make sure categories is valid
    assert (restricted_categories.find(my_category) != restricted_categories.end());
    categories.insert(my_category);
  }

  // --------------------------------------------------------------
  // HELPER UTILTIY PROGRAMS
  if (my_program == "/bin/cp") {
    categories.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
  }
  else if (my_program == "/bin/mv") {
    categories.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
    categories.insert("FILE_MANAGEMENT_MOVE_DELETE_RENAME_FILE_DIRECTORY");
  }
  else if (my_program == "/usr/bin/time") {
    categories.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
  }

  else if (my_program == "/usr/bin/strace") {
    categories = restricted_categories;
  } 
  
  // ---------------------------------------------------------------
  // SUBMITTY ANALYSIS TOOLS
  else if (my_program == SUBMITTY_INSTALL_DIRECTORY+"/SubmittyAnalysisTools/count") {
    //TODO
    categories = restricted_categories;
    categories.insert("COMMUNICATIONS_AND_NETWORKING_SIGNALS");
    categories.insert("FILE_MANAGEMENT_RARE");
    categories.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
    categories.insert("COMMUNICATIONS_AND_NETWORKING_INTERPROCESS_COMMUNICATION");
  }

  // ---------------------------------------------------------------
  // PYTHON 
  else if (my_program.find("/usr/bin/python") != std::string::npos) {
    categories = restricted_categories; //TODO: fix
    categories.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
    categories.insert("COMMUNICATIONS_AND_NETWORKING_SIGNALS");
    categories.insert("FILE_MANAGEMENT_RARE");
    categories.insert("COMMUNICATIONS_AND_NETWORKING_SOCKETS_MINIMAL");
  }
  
  // ---------------------------------------------------------------
  // C/C++ COMPILATION
  else if (my_program == "/usr/bin/g++" ||
           my_program == "/usr/bin/clang++" ||
           my_program == "/usr/bin/gcc") {
    categories.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
    categories.insert("FILE_MANAGEMENT_MOVE_DELETE_RENAME_FILE_DIRECTORY");
    categories.insert("FILE_MANAGEMENT_PERMISSIONS");
    categories.insert("FILE_MANAGEMENT_RARE");
    categories.insert("PROCESS_CONTROL_ADVANCED");
    categories.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
    categories.insert("TGKILL");
  }

  // ---------------------------------------------------------------
  // CMAKE/MAKE COMPILATION
  else if (my_program == "/usr/bin/cmake" ||
           my_program == "/usr/bin/make") {
    categories = restricted_categories;
  }

  // ---------------------------------------------------------------
  // JAVA COMPILATION
  else if (my_program == "/usr/bin/javac") {
    categories.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
    categories.insert("PROCESS_CONTROL_MEMORY_ADVANCED");
    categories.insert("PROCESS_CONTROL_SYNCHRONIZATION");
    categories.insert("PROCESS_CONTROL_SCHEDULING");
    categories.insert("PROCESS_CONTROL_ADVANCED");
    categories.insert("FILE_MANAGEMENT_MOVE_DELETE_RENAME_FILE_DIRECTORY");
    categories.insert("FILE_MANAGEMENT_PERMISSIONS");
    categories.insert("FILE_MANAGEMENT_CAPABILITIES");
    categories.insert("FILE_MANAGEMENT_EXTENDED_ATTRIBUTES");
    categories.insert("FILE_MANAGEMENT_RARE");
    categories.insert("INFORMATION_MAINTENANCE_ADVANCED");
    categories.insert("COMMUNICATIONS_AND_NETWORKING_SOCKETS_MINIMAL");
    categories.insert("COMMUNICATIONS_AND_NETWORKING_SOCKETS");
    categories.insert("COMMUNICATIONS_AND_NETWORKING_SIGNALS");
    categories.insert("COMMUNICATIONS_AND_NETWORKING_INTERPROCESS_COMMUNICATION");
    categories.insert("TGKILL");
    categories.insert("COMMUNICATIONS_AND_NETWORKING_KILL");
    categories.insert("UNKNOWN");
    categories.insert("UNKNOWN_MODULE");
  }

  // ---------------------------------------------------------------
  // JAVA
  else if (my_program == "/usr/bin/java") {
    categories.insert("COMMUNICATIONS_AND_NETWORKING_SIGNALS");
    categories.insert("COMMUNICATIONS_AND_NETWORKING_SOCKETS_MINIMAL");
    categories.insert("COMMUNICATIONS_AND_NETWORKING_SOCKETS");
    categories.insert("FILE_MANAGEMENT_MOVE_DELETE_RENAME_FILE_DIRECTORY");
    categories.insert("FILE_MANAGEMENT_PERMISSIONS");
    categories.insert("FILE_MANAGEMENT_RARE");
    categories.insert("PROCESS_CONTROL_ADVANCED");
    categories.insert("PROCESS_CONTROL_GET_SET_USER_GROUP_ID");
    categories.insert("PROCESS_CONTROL_MEMORY_ADVANCED");
    categories.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
    categories.insert("PROCESS_CONTROL_SCHEDULING");
    categories.insert("PROCESS_CONTROL_SYNCHRONIZATION");
    categories.insert("FILE_MANAGEMENT_CAPABILITIES");
    categories.insert("FILE_MANAGEMENT_EXTENDED_ATTRIBUTES");
    categories.insert("INFORMATION_MAINTENANCE_ADVANCED");
    categories.insert("COMMUNICATIONS_AND_NETWORKING_INTERPROCESS_COMMUNICATION");
    categories.insert("TGKILL");
    categories.insert("COMMUNICATIONS_AND_NETWORKING_KILL");
    categories.insert("UNKNOWN");
    categories.insert("UNKNOWN_MODULE");
  }
  
  // ---------------------------------------------------------------
  // SWI PROLOG
  else if (my_program == "/usr/bin/swipl") {
    categories.insert("FILE_MANAGEMENT_PERMISSIONS");
    categories.insert("FILE_MANAGEMENT_RARE");
    categories.insert("PROCESS_CONTROL_ADVANCED");
    categories.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
  }

  // RACKET SCHEME
  else if (my_program == "/usr/bin/plt-r5rs") {
    categories.insert("COMMUNICATIONS_AND_NETWORKING_INTERPROCESS_COMMUNICATION");
    categories.insert("COMMUNICATIONS_AND_NETWORKING_SIGNALS");
    categories.insert("FILE_MANAGEMENT_PERMISSIONS");
    categories.insert("FILE_MANAGEMENT_RARE");
    categories.insert("PROCESS_CONTROL_ADVANCED");
    categories.insert("PROCESS_CONTROL_GET_SET_USER_GROUP_ID");
    categories.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
    categories.insert("PROCESS_CONTROL_SYNCHRONIZATION");
  }

  // ---------------------------------------------------------------
  // C++ Memory Debugging
  // FIXME: update with the actual dr memory install location?
  else if (my_program.find("drmemory") != std::string::npos || 
           my_program.find("valgrind") != std::string::npos) {
    categories.insert("COMMUNICATIONS_AND_NETWORKING_SIGNALS");
    categories.insert("COMMUNICATIONS_AND_NETWORKING_INTERPROCESS_COMMUNICATION");
    categories.insert("FILE_MANAGEMENT_EXTENDED_ATTRIBUTES");
    categories.insert("FILE_MANAGEMENT_MOVE_DELETE_RENAME_FILE_DIRECTORY");
    categories.insert("FILE_MANAGEMENT_PERMISSIONS");
    categories.insert("FILE_MANAGEMENT_RARE");
    categories.insert("PROCESS_CONTROL_ADVANCED");
    categories.insert("PROCESS_CONTROL_GET_SET_USER_GROUP_ID");
    categories.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
    categories.insert("PROCESS_CONTROL_SYNCHRONIZATION");
    categories.insert("DEVICE_MANAGEMENT_NEW_DEVICE");
    categories.insert("TGKILL");
  } 

  // ---------------------------------------------------------------
  // IMAGE COMPARISON
  else if (my_program == "/usr/bin/compare") {
    categories.insert("FILE_MANAGEMENT_PERMISSIONS");
    categories.insert("PROCESS_CONTROL_ADVANCED");
    categories.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
    categories.insert("PROCESS_CONTROL_SCHEDULING");
  }

  // ---------------------------------------------------------------
  else if (my_program == "/usr/bin/sort") {
    categories.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
    categories.insert("PROCESS_CONTROL_SCHEDULING");
  }

  // ---------------------------------------------------------------
  //KEYBOARD INPUT
  else if(my_program == "/usr/bin/xdotool"){
    categories = restricted_categories; //TODO: fix
  }

  // ---------------------------------------------------------------
  //WINDOW FOCUS
  else if(my_program == "/usr/bin/wmctrl"){
    categories = restricted_categories; //TODO: fix
  }

  // ---------------------------------------------------------------
  //WINDOW INFORMATION
  else if(my_program == "/usr/bin/xwininfo"){
    categories = restricted_categories; //TODO: fix
  }

  // ---------------------------------------------------------------
  //SCREENSHOT FUNCTIONALITY
  else if(my_program == "/usr/bin/scrot"){
    categories = restricted_categories; //TODO: fix
  }

  else {
    categories = restricted_categories; //TODO: fix
    // UGH, don't want this here
    categories.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
  }

  // make sure all categories are valid
  for_each(categories.begin(),categories.end(),
           [restricted_categories](const std::string &s){
             assert (restricted_categories.find(s) != restricted_categories.end()); });
  
  allow_system_calls(sc,categories);

  if (seccomp_load(sc) < 0)
    return 1; // failure                                                                                   
  
  /* This does not remove the filter */
  seccomp_release(sc);

  return 0;
}


// ===========================================================================
// ===========================================================================
