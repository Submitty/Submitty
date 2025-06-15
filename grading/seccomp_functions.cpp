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
#include <iomanip>

// COMPILATION NOTE: Must pass -lseccomp to build
#ifndef __NR_rseq
# ifdef __x86_64__
#  define __NR_rseq 334
# else
#  define __NR_rseq 386
# endif
#endif
#include <seccomp.h>
#include <set>
#include <map>
#include <string>
#include <seccomp.h>
#include <iostream>
#include <fstream>
#include <vector>
#include <string>

#define SUBMITTY_INSTALL_DIRECTORY  std::string("__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__")

#define ALLOW_SYSCALL(name, which_category)  allow_syscall(sc,SCMP_SYS(name),#name,execute_logfile, which_category,categories)
#define ALLOW_SYSCALL_BY_NUMBER(num, name, which_category)  allow_syscall(sc,num,name,execute_logfile, which_category,categories)

static std::map<int,std::pair<std::string,bool> > allowed_system_calls;


inline void allow_syscall(scmp_filter_ctx sc, int syscall, const std::string &syscall_string, std::ofstream &execute_logfile,
                          const std::string &which_category, const std::set<std::string> &categories) {
  bool allowed = false;
  if (which_category.find("SAFELIST:") != std::string::npos)
    allowed = true;
  else if (which_category.find("FORBIDDEN:") != std::string::npos)
    allowed = false;
  else {
    assert (which_category.find("RESTRICTED:") != std::string::npos);
    if (categories.find(which_category.substr(11,which_category.size()-11)) != categories.end()) {
      allowed = true;
    }
  }
  allowed_system_calls.insert(std::make_pair(syscall,std::make_pair(syscall_string,allowed)));
}

void process_allow_system_calls(scmp_filter_ctx sc, std::ofstream &execute_logfile) {
  for (std::map<int,std::pair<std::string,bool> >::iterator itr = allowed_system_calls.begin(); itr != allowed_system_calls.end(); itr++) {
    if (itr->second.second == false) {
      execute_logfile << "              DISALLOWED " << itr->first << " " << itr->second.first << std::endl;
    } else {
      int res = seccomp_rule_add(sc, SCMP_ACT_ALLOW, itr->first, 0);
      if (res < 0) {
        execute_logfile << "WARNING:  Errno " << res << " installing seccomp rule for " << itr->first << std::endl;
      }
      execute_logfile << "allowed                  " << itr->first << " " << itr->second.first << std::endl;
    }
  }
  execute_logfile << std::endl;
}

void scan_allowed_system_calls(scmp_filter_ctx sc, std::ofstream &execute_logfile) {
  for (int i = 0; i < 1100; i++) {
    execute_logfile << "BY NUMBER " << i << " ";
    if (allowed_system_calls.find(i) != allowed_system_calls.end()) {
      execute_logfile << " ... already added " << std::endl;
    } else {
      execute_logfile << "                            MISSING THIS ONE" << std::endl;
    }
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
#include <nlohmann/json.hpp>

// ===========================================================================
// ===========================================================================

std::set<std::string> system_call_categories_based_on_program
(const std::string &my_program, const std::set<std::string> &restricted_categories) {

  std::set<std::string> default_categories = {
    "PROCESS_CONTROL_NEW_PROCESS_THREAD",
    "PROCESS_CONTROL_SCHEDULING",
    "PROCESS_CONTROL_ADVANCED",
    "FILE_MANAGEMENT_MOVE_DELETE_RENAME_FILE_DIRECTORY",
    "FILE_MANAGEMENT_PERMISSIONS",
    "FILE_MANAGEMENT_RARE",
    "COMMUNICATIONS_AND_NETWORKING_SOCKETS_MINIMAL",
    "COMMUNICATIONS_AND_NETWORKING_SIGNALS",
    "COMMUNICATIONS_AND_NETWORKING_INTERPROCESS_COMMUNICATION",
    "TGKILL"
  };

  std::set<std::string> answer;

  // --------------------------------------------------------------
  // HELPER UTILTIY PROGRAMS
  if (my_program == "/bin/cp") {
    answer.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
    answer.insert("PROCESS_CONTROL_ADVANCED");
  }
  else if (my_program == "/bin/mv") {
    answer.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
    answer.insert("FILE_MANAGEMENT_MOVE_DELETE_RENAME_FILE_DIRECTORY");
    answer.insert("PROCESS_CONTROL_ADVANCED");
  }
  else if (my_program == "/usr/bin/time") {
    answer.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
  }
  else if (my_program == "/usr/bin/strace") {
    answer = restricted_categories;
  }

  // ---------------------------------------------------------------
  // SUBMITTY ANALYSIS TOOLS
  else if (my_program == SUBMITTY_INSTALL_DIRECTORY+"/SubmittyAnalysisTools/count") {
    //TODO
    answer = restricted_categories;
    //    answer.insert("COMMUNICATIONS_AND_NETWORKING_SIGNALS");
    //answer.insert("FILE_MANAGEMENT_RARE");
    //answer.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
    //answer.insert("COMMUNICATIONS_AND_NETWORKING_INTERPROCESS_COMMUNICATION");
  }

  // ---------------------------------------------------------------
  // PYTHON
  else if (my_program.find("/usr/bin/python") != std::string::npos) {
    answer = restricted_categories; //TODO: fix
    //answer.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
    //answer.insert("COMMUNICATIONS_AND_NETWORKING_SIGNALS");
    //answer.insert("FILE_MANAGEMENT_RARE");
    //answer.insert("COMMUNICATIONS_AND_NETWORKING_SOCKETS_MINIMAL");
    //answer.insert("PROCESS_CONTROL_ADVANCED");
  }

  // ---------------------------------------------------------------
  // C/C++ COMPILATION
  else if (my_program == "/usr/bin/g++" ||
           my_program == "/usr/bin/gcc" ||
           my_program.find("/usr/bin/clang") != std::string::npos) {
    answer.insert("FILE_MANAGEMENT_MOVE_DELETE_RENAME_FILE_DIRECTORY");
    answer.insert("FILE_MANAGEMENT_PERMISSIONS");
    answer.insert("FILE_MANAGEMENT_RARE");
    answer.insert("PROCESS_CONTROL_ADVANCED");
    answer.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
    answer.insert("TGKILL");
    answer.insert("COMMUNICATIONS_AND_NETWORKING_SIGNALS");
    answer.insert("COMMUNICATIONS_AND_NETWORKING_INTERPROCESS_COMMUNICATION");
    answer.insert("COMMUNICATIONS_AND_NETWORKING_SOCKETS_MINIMAL");
    answer.insert("UNKNOWN");
  }

  // ---------------------------------------------------------------
  // CMAKE/MAKE COMPILATION
  else if (my_program == "/usr/bin/cmake" ||
           my_program == "/usr/bin/make") {
    answer = restricted_categories;
  }

  // ---------------------------------------------------------------
  // JAVA COMPILATION
  else if (my_program == "/usr/bin/javac") {
    answer.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
    answer.insert("PROCESS_CONTROL_MEMORY_ADVANCED");
    answer.insert("PROCESS_CONTROL_SYNCHRONIZATION");
    answer.insert("PROCESS_CONTROL_SCHEDULING");
    answer.insert("PROCESS_CONTROL_ADVANCED");
    answer.insert("FILE_MANAGEMENT_MOVE_DELETE_RENAME_FILE_DIRECTORY");
    answer.insert("FILE_MANAGEMENT_PERMISSIONS");
    answer.insert("FILE_MANAGEMENT_CAPABILITIES");
    answer.insert("FILE_MANAGEMENT_EXTENDED_ATTRIBUTES");
    answer.insert("FILE_MANAGEMENT_RARE");
    answer.insert("INFORMATION_MAINTENANCE_ADVANCED");
    answer.insert("COMMUNICATIONS_AND_NETWORKING_SOCKETS_MINIMAL");
    answer.insert("COMMUNICATIONS_AND_NETWORKING_SOCKETS");
    answer.insert("COMMUNICATIONS_AND_NETWORKING_SIGNALS");
    answer.insert("COMMUNICATIONS_AND_NETWORKING_INTERPROCESS_COMMUNICATION");
    answer.insert("TGKILL");
    answer.insert("COMMUNICATIONS_AND_NETWORKING_KILL");
    answer.insert("UNKNOWN");
    answer.insert("UNKNOWN_MODULE");
  }

  // ---------------------------------------------------------------
  // JAVA
  else if (my_program == "/usr/bin/java") {
    answer.insert("COMMUNICATIONS_AND_NETWORKING_SIGNALS");
    answer.insert("COMMUNICATIONS_AND_NETWORKING_SOCKETS_MINIMAL");
    answer.insert("COMMUNICATIONS_AND_NETWORKING_SOCKETS");
    answer.insert("FILE_MANAGEMENT_MOVE_DELETE_RENAME_FILE_DIRECTORY");
    answer.insert("FILE_MANAGEMENT_PERMISSIONS");
    answer.insert("FILE_MANAGEMENT_RARE");
    answer.insert("PROCESS_CONTROL_ADVANCED");
    answer.insert("PROCESS_CONTROL_GET_SET_USER_GROUP_ID");
    answer.insert("PROCESS_CONTROL_MEMORY_ADVANCED");
    answer.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
    answer.insert("PROCESS_CONTROL_SCHEDULING");
    answer.insert("PROCESS_CONTROL_SYNCHRONIZATION");
    answer.insert("FILE_MANAGEMENT_CAPABILITIES");
    answer.insert("FILE_MANAGEMENT_EXTENDED_ATTRIBUTES");
    answer.insert("INFORMATION_MAINTENANCE_ADVANCED");
    answer.insert("COMMUNICATIONS_AND_NETWORKING_INTERPROCESS_COMMUNICATION");
    answer.insert("TGKILL");
    answer.insert("COMMUNICATIONS_AND_NETWORKING_KILL");
    answer.insert("UNKNOWN");
    answer.insert("UNKNOWN_MODULE");
  }

  // ---------------------------------------------------------------
  // C++ Memory Debugging
  // FIXME: update with the actual dr memory install location?
  else if (my_program.find("drmemory") != std::string::npos ||
           my_program.find("valgrind") != std::string::npos) {
    answer.insert("COMMUNICATIONS_AND_NETWORKING_SIGNALS");
    answer.insert("COMMUNICATIONS_AND_NETWORKING_INTERPROCESS_COMMUNICATION");
    answer.insert("COMMUNICATIONS_AND_NETWORKING_KILL");
    answer.insert("FILE_MANAGEMENT_EXTENDED_ATTRIBUTES");
    answer.insert("FILE_MANAGEMENT_MOVE_DELETE_RENAME_FILE_DIRECTORY");
    answer.insert("FILE_MANAGEMENT_PERMISSIONS");
    answer.insert("FILE_MANAGEMENT_RARE");
    answer.insert("PROCESS_CONTROL_ADVANCED");
    answer.insert("PROCESS_CONTROL_GET_SET_USER_GROUP_ID");
    answer.insert("PROCESS_CONTROL_NEW_PROCESS_THREAD");
    answer.insert("PROCESS_CONTROL_SYNCHRONIZATION");
    answer.insert("DEVICE_MANAGEMENT_NEW_DEVICE");
    answer.insert("TGKILL");
  }

  else {
    answer = default_categories;
  }
  
  return answer;
}


// ===========================================================================
// ===========================================================================

int install_syscall_filter(bool is_32, const std::string &my_program, std::ofstream &execute_logfile,
                           const nlohmann::json &whole_config, const nlohmann::json &test_case_config) {
 
  int res;
  scmp_filter_ctx sc = seccomp_init(SCMP_ACT_KILL);
  //scmp_filter_ctx sc = seccomp_init(SCMP_ACT_LOG);
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


  // grep ' :' grading/system_call_categories.cpp | grep SAFELIST | cut -f 6 -d ' '
  // grep ' :' grading/system_call_categories.cpp | grep RESTRICTED | cut -f 6 -d ' '
  // grep ' :' grading/system_call_categories.cpp | grep FORBIDDEN | cut -f 6 -d ' '

  std::set<std::string> safelist_categories = {
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
  
  std::set<std::string> default_categories =
    system_call_categories_based_on_program(my_program,restricted_categories);
  //execute_logfile << "categories based on program " << default_categories.size() << std::endl;

  // ---------------------------------------------------------------
  // READ ALLOWED SYSTEM CALLS FROM CONFIG.JSON
  nlohmann::json whole_config_safelist = whole_config.value("allow_system_calls",default_categories);

  //execute_logfile << "categories based on global config " << whole_config_safelist.size() << std::endl;
  
  nlohmann::json config_safelist = whole_config_safelist;
  try {
    config_safelist = test_case_config.value("allow_system_calls",whole_config_safelist);
  } catch (...) {
    // custom_doit validation don't have a valid test_case_config json object
  }
  //execute_logfile << "categories based on test case config " << config_safelist.size() << std::endl;

  std::set<std::string> categories;
  if (config_safelist.size() == 1 &&
      *config_safelist.begin() == "ALLOW_ALL_RESTRICTED_SYSTEM_CALLS") {
    categories = restricted_categories;
  } else {
    for (nlohmann::json::const_iterator cwitr = config_safelist.begin();
         cwitr != config_safelist.end(); cwitr++) {
      //for (nlohmann::json::const_iterator cwitr = categories.begin();
      // cwitr != categories.end(); cwitr++) {
      std::string my_category = *cwitr;
      if (my_category.size() > 27 && my_category.substr(0,27) == "ALLOW_SYSTEM_CALL_CATEGORY_") {
        my_category = my_category.substr(27,my_category.size()-27);
        std::cout << " typo in system call category name " << my_category << std::endl;
        assert(0);
      }
      // make sure categories is valid
      assert (restricted_categories.find(my_category) != restricted_categories.end());
      categories.insert(my_category);
    }
  }

  // make sure all categories are valid
  for_each(categories.begin(),categories.end(),
           [restricted_categories](const std::string &s){
             assert (restricted_categories.find(s) != restricted_categories.end()); });

  allow_system_calls(sc,categories,execute_logfile);
  process_allow_system_calls(sc,execute_logfile);
  scan_allowed_system_calls(sc,execute_logfile);
  
  if (seccomp_load(sc) < 0)
    return 1; // failure

  /* This does not remove the filter */
  seccomp_release(sc);

  return 0;
}


// ===========================================================================
// ===========================================================================
