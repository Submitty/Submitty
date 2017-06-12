#include <sys/types.h>
#include <sys/stat.h>
#include <cstdio>
#include <cstddef>
#include <cstdlib>
#include <unistd.h>
#include <fcntl.h>
#include <elf.h>

// COMPILATION NOTE: Must pass -lseccomp to build
#include <seccomp.h>
#include <set>
#include <string>
#include <seccomp.h>
#include <iostream>
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

// ===========================================================================
// ===========================================================================

int install_syscall_filter(bool is_32, const std::string &my_program, std::ofstream &execute_logfile) {

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

  // DESPERATE, WHITELIST EVERYTHING
  // FIXME: determine what is non-deterministic about system calls for compilation :(
  categories.insert("PROCESS_CONTROL_MEMORY_ADVANCED");
  categories.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
  categories.insert("PROCESS_CONTROL_SYNCHRONIZATION");
  categories.insert("PROCESS_CONTROL_SCHEDULING");
  /*
  categories.insert("PROCESS_CONTROL_ADVANCED");
  categories.insert("PROCESS_CONTROL_GET_SET_USER_GROUP_ID");
  categories.insert("FILE_MANAGEMENT_MOVE_DELETE_RENAME_FILE_DIRECTORY");

  categories.insert("FILE_MANAGEMENT_PERMISSIONS");
  categories.insert("FILE_MANAGEMENT_CAPABILITIES");
  categories.insert("FILE_MANAGEMENT_EXTENDED_ATTRIBUTES");
  categories.insert("FILE_MANAGEMENT_RARE");

  categories.insert("DEVICE_MANAGEMENT_ADVANCED");
  categories.insert("DEVICE_MANAGEMENT_NEW_DEVICE");
  categories.insert("INFORMATION_MAINTENANCE_ADVANCED");
  categories.insert("COMMUNICATIONS_AND_NETWORKING_SOCKETS_MINIMAL");
  categories.insert("COMMUNICATIONS_AND_NETWORKING_SOCKETS");
  categories.insert("COMMUNICATIONS_AND_NETWORKING_SIGNALS");
  categories.insert("COMMUNICATIONS_AND_NETWORKING_INTERPROCESS_COMMUNICATION");
  */
  //categories.insert("TGKILL");
  //categories.insert("COMMUNICATIONS_AND_NETWORKING_KILL");
  categories.insert("UNKNOWN");
  categories.insert("UNKNOWN_MODULE");
  categories.insert("UNKNOWN_REMAP_PAGES");
  
  
  // C/C++ COMPILATION
  if (my_program == "/usr/bin/g++" ||
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

  
  // SWI PROLOG
  if (my_program == "/usr/bin/swipl") {
    categories.insert("FILE_MANAGEMENT_PERMISSIONS");
    categories.insert("FILE_MANAGEMENT_RARE");
    categories.insert("PROCESS_CONTROL_ADVANCED");
    categories.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
  }

  // RACKET SCHEME
  if (my_program == "/usr/bin/plt-r5rs") {
    categories.insert("COMMUNICATIONS_AND_NETWORKING_INTERPROCESS_COMMUNICATION");
    categories.insert("COMMUNICATIONS_AND_NETWORKING_SIGNALS");
    categories.insert("FILE_MANAGEMENT_PERMISSIONS");
    categories.insert("FILE_MANAGEMENT_RARE");
    categories.insert("PROCESS_CONTROL_ADVANCED");
    categories.insert("PROCESS_CONTROL_GET_SET_USER_GROUP_ID");
    categories.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
    categories.insert("PROCESS_CONTROL_SYNCHRONIZATION");
  }


  // PYTHON 
  if (my_program == "/usr/bin/python") {
    categories.insert("FILE_MANAGEMENT_PERMISSIONS");
    categories.insert("PROCESS_CONTROL_ADVANCED");
    categories.insert("PROCESS_CONTROL_GET_SET_USER_GROUP_ID");
    categories.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
    categories.insert("PROCESS_CONTROL_MEMORY_ADVANCED");
    categories.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
    categories.insert("PROCESS_CONTROL_SCHEDULING");
  }


  if (my_program == "/usr/bin/java") {
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
  }
  

  // JAVA
  if (my_program == "/usr/bin/javac") {
    categories.insert("COMMUNICATIONS_AND_NETWORKING_SIGNALS");
    categories.insert("COMMUNICATIONS_AND_NETWORKING_SOCKETS_MINIMAL");
    categories.insert("FILE_MANAGEMENT_PERMISSIONS");
    categories.insert("FILE_MANAGEMENT_RARE");
    categories.insert("PROCESS_CONTROL_ADVANCED");
    categories.insert("PROCESS_CONTROL_GET_SET_USER_GROUP_ID");
    categories.insert("PROCESS_CONTROL_MEMORY_ADVANCED");
    categories.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
    categories.insert("PROCESS_CONTROL_SCHEDULING");
    categories.insert("PROCESS_CONTROL_SYNCHRONIZATION");

    ///*
    categories.insert("FILE_MANAGEMENT_MOVE_DELETE_RENAME_FILE_DIRECTORY");
    categories.insert("FILE_MANAGEMENT_CAPABILITIES");
    categories.insert("FILE_MANAGEMENT_EXTENDED_ATTRIBUTES");
    categories.insert("DEVICE_MANAGEMENT_ADVANCED");
    categories.insert("DEVICE_MANAGEMENT_NEW_DEVICE");
    categories.insert("INFORMATION_MAINTENANCE_ADVANCED");
    categories.insert("COMMUNICATIONS_AND_NETWORKING_SOCKETS");
    categories.insert("COMMUNICATIONS_AND_NETWORKING_INTERPROCESS_COMMUNICATION");
    //*/
    categories.insert("TGKILL");
    categories.insert("COMMUNICATIONS_AND_NETWORKING_KILL");
    categories.insert("UNKNOWN");
    categories.insert("UNKNOWN_MODULE");
    categories.insert("UNKNOWN_REMAP_PAGES");

  }

  // HELPER UTILTIY PROGRAMS
  if (my_program == "/usr/bin/time") {
    categories.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
  } 

  // C++ Memory Debugging
  // FIXME: update with the actual dr memory install location?
  if (my_program.find("drmemory") != std::string::npos || 
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
  } 

  // IMAGE COMPARISON
  if (my_program == "/usr/bin/compare") {
    categories.insert("FILE_MANAGEMENT_PERMISSIONS");
    categories.insert("PROCESS_CONTROL_ADVANCED");
    categories.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
    categories.insert("PROCESS_CONTROL_SCHEDULING");
  }

  if (my_program == SUBMITTY_INSTALL_DIRECTORY+"/SubmittyAnalysisTools/bin/count_node" ||
      my_program == SUBMITTY_INSTALL_DIRECTORY+"/SubmittyAnalysisTools/bin/count_function" ||
      my_program == SUBMITTY_INSTALL_DIRECTORY+"/SubmittyAnalysisTools/bin/count_token") {
    categories.insert("COMMUNICATIONS_AND_NETWORKING_INTERPROCESS_COMMUNICATION");
    categories.insert("COMMUNICATIONS_AND_NETWORKING_SIGNALS");
    categories.insert("FILE_MANAGEMENT_PERMISSIONS");
    categories.insert("FILE_MANAGEMENT_RARE");
    categories.insert("PROCESS_CONTROL_ADVANCED");
    categories.insert("PROCESS_CONTROL_GET_SET_USER_GROUP_ID");
    categories.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
    categories.insert("UNKNOWN");
  }

  allow_system_calls(sc,categories);

  if (seccomp_load(sc) < 0)
    return 1; // failure                                                                                   
  
  /* This does not remove the filter */
  seccomp_release(sc);

  return 0;
}


// ===========================================================================
// ===========================================================================
