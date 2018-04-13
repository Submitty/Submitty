#include <sys/time.h>
#include <sys/resource.h>

#include <string>
#include <vector>
#include <cassert>
#include <iostream>
#include <map>

#include "execute.h"
#include "json.hpp"

// =====================================================================================

// documentation on setrlimit
// http://linux.die.net/man/2/setrlimit

// =====================================================================================

const std::vector<int> limit_names = { 
  RLIMIT_CPU,        //  0  CPU time (not wall clock) in seconds
  RLIMIT_FSIZE,      //  1  created file size in bytes (includes total file size after appending)
  RLIMIT_DATA,       //  2  heap size in bytes (includes initialized & uninitialized data segment) 
  RLIMIT_STACK,      //  3  stack size in bytes (also includes command line arguments and environment variables)
  RLIMIT_CORE,       //  4  allow core files?  core file size in bytes
  RLIMIT_RSS,        //  5  limit in pages of resident set (deprecated?)
  RLIMIT_NPROC,      //  6  number of processes (threads) that can be created
  RLIMIT_NOFILE,     //  7  1 + maximum number of file descriptors
  RLIMIT_MEMLOCK,    //  8  bytes of memory that may be locked into RAM
  RLIMIT_AS,         //  9  virtual memory (address space) in bytes (2GB max or unlimited)
  RLIMIT_LOCKS,      // 10  number of file locks 
  RLIMIT_SIGPENDING, // 11  number of signals that may be queued
  RLIMIT_MSGQUEUE,   // 12  bytes of memory allocated to POSIX message queues
  RLIMIT_NICE,       // 13  ceiling of nice value 
  RLIMIT_RTPRIO,     // 14  real-time priority
  RLIMIT_RTTIME      // 15  limit in microseconds for real-time scheduling without blocking system call 
};

// =====================================================================================

// NOTE:   RLIM_INFINITY = 18446744073709551615

// Instructor configurations (assignment_limits and test_case_limits)
// cannot exceed these values
const std::map<int,rlim_t> system_limits = 
  { 
    { RLIMIT_CPU,        600              }, // 10 minutes per test
    { RLIMIT_FSIZE,      100*1000*1000    }, // 100 MB created file size
    { RLIMIT_DATA,       RLIM_INFINITY    }, // heap                // 1 GB
    { RLIMIT_STACK,      RLIM_INFINITY    }, // stack size          // 50 MB
    { RLIMIT_CORE,       RLIM_INFINITY    }, // allow core files?   // FIXME: 0
    { RLIMIT_RSS,        RLIM_INFINITY    }, //      (deprecated, use AS instead?)
    { RLIMIT_NPROC,      10000            }, // 10000 additional processes
    { RLIMIT_NOFILE,     RLIM_INFINITY    }, // 1000 file descriptors 
    { RLIMIT_MEMLOCK,    RLIM_INFINITY    }, // 2GB RAM 

    //{ RLIMIT_NOFILE,     1000             }, // 1000 file descriptors 
    //{ RLIMIT_MEMLOCK,    2*1000*1000*1000 }, // 2GB RAM 

    { RLIMIT_AS,         RLIM_INFINITY    }, //    2 GB
    { RLIMIT_LOCKS,      RLIM_INFINITY    }, //   100
    { RLIMIT_SIGPENDING, RLIM_INFINITY    }, // 0
    { RLIMIT_MSGQUEUE,   RLIM_INFINITY    }, // 0
    { RLIMIT_NICE,       RLIM_INFINITY    }, // 
    { RLIMIT_RTPRIO,     RLIM_INFINITY    }, // 0
    { RLIMIT_RTTIME,     RLIM_INFINITY    }  // 0
  };


std::string rlimit_name_decoder(int i) {
  if (i == RLIMIT_CPU)        { return "RLIMIT_CPU"; }      
  if (i == RLIMIT_FSIZE)      { return "RLIMIT_FSIZE"; }  
  if (i == RLIMIT_DATA)       { return "RLIMIT_DATA"; }   
  if (i == RLIMIT_STACK)      { return "RLIMIT_STACK"; }  
  if (i == RLIMIT_CORE)       { return "RLIMIT_CORE"; }   
  if (i == RLIMIT_RSS)        { return "RLIMIT_RSS"; }    
  if (i == RLIMIT_NPROC)      { return "RLIMIT_NPROC"; }  
  if (i == RLIMIT_NOFILE)     { return "RLIMIT_NOFILE"; } 
  if (i == RLIMIT_MEMLOCK)    { return "RLIMIT_MEMLOCK"; }
  if (i == RLIMIT_AS)         { return "RLIMIT_AS"; }     
  if (i == RLIMIT_LOCKS)      { return "RLIMIT_LOCKS"; }  
  if (i == RLIMIT_SIGPENDING) { return "RLIMIT_SIGPENDING"; }
  if (i == RLIMIT_MSGQUEUE)   { return "RLIMIT_MSGQUEUE"; }  
  if (i == RLIMIT_NICE)       { return "RLIMIT_NICE"; }      
  if (i == RLIMIT_RTPRIO)     { return "RLIMIT_RTPRIO"; }    
  if (i == RLIMIT_RTTIME)     { return "RLIMIT_RTTIME"; }
  return "UNKNOWN RLIMIT NAME";
};


extern const std::map<int,rlim_t> default_limits;  // defined in default_config.h

// =====================================================================================
// 
// Set limits on the executing process for running time, size of
// produced files, etc.  These can be adjusted per assignment.
//
// =====================================================================================


void CheckResourceLimits(nlohmann::json &resource_limits) {
  nlohmann::json::iterator itr = resource_limits.begin();
  while (itr != resource_limits.end()) {
    // make sure the resource limit names are correctly spelled
    bool valid = false;
    for (int i = 0; i < 16; i++) {
      if (rlimit_name_decoder(i) == itr.key()) {
        valid = true;
        break;
      }
    }
    if (!valid) {
      std::cerr << "ERROR! INVALID RESOURCE LIMIT: " << itr.key();
      exit(1);
    }
    // the only non number value allowed is for infinity
    if (itr.value().type() == nlohmann::json::value_t::string) {
      assert (itr.value() == "RLIM_INFINITY");
      // replace the string with the integer value
      itr.value() = RLIM_INFINITY;
    }
    assert (itr.value().is_number());
    itr++;
  }
}


rlim_t get_the_limit(const std::string &program_name,
         int which_limit,
                     const nlohmann::json &test_case_limits_const,
                     const nlohmann::json &assignment_limits_const) {

  // explicitly copy these so we can edit them....
  nlohmann::json test_case_limits = test_case_limits_const;
  nlohmann::json assignment_limits = assignment_limits_const;

  CheckResourceLimits(test_case_limits);
  CheckResourceLimits(assignment_limits);
  
  std::string which_limit_name = rlimit_name_decoder(which_limit);


  // first, grab the system limit (this value must exist)
  std::map<int,rlim_t>::const_iterator s_itr = system_limits.find(which_limit);
  assert (s_itr != system_limits.end());


  // then, look for a test case specific value
  nlohmann::json::iterator t_itr = test_case_limits.find(which_limit_name);
  if (t_itr != test_case_limits.end()) {
    int val = test_case_limits[which_limit_name];
    // check to see if the test case specific value exceeds the system limit
    if (val > s_itr->second) {
      std::cout << "ERROR: Test_Case limit value " << val
        << " for " << which_limit_name
        << " exceeds system limit " << s_itr->second << std::endl;
      return s_itr->second;
    } else {
      return val;
    }
  }


  // otherwise look for an assignment specific value
  nlohmann::json::iterator a_itr = assignment_limits.find(which_limit_name);
  if (a_itr != assignment_limits.end()) {
    int val = assignment_limits[which_limit_name];
    // check to see if the test case specific value exceeds the system limit
    if (val > s_itr->second) {
      std::cout << "ERROR: Assignment limit value " << val
        << " for " << which_limit_name
        << " exceeds system limit " << s_itr->second << std::endl;
      return s_itr->second;
    } else {
      return val;
    }
  }


  // then, grab the default value (this value must also exist)
  // (it might be the default defined in config.json
  //  or it might be instructor defined in config.json)
  std::map<int,rlim_t>::const_iterator d_itr = default_limits.find(which_limit);
  assert (d_itr != default_limits.end());

  // default value must not exceed system limit
  assert (d_itr->second <= s_itr->second);

  return d_itr->second;
}





// =====================================================================================

void enable_all_setrlimit(const std::string &program_name,
                          const nlohmann::json &test_case_limits,
                          const nlohmann::json &assignment_limits) {

  int success;
  rlimit current_rl;
  rlimit new_rl;
  
  //std::cout << "SETTING LIMITS FOR PROCESS! " << program_name << std::endl;
  
  // loop over all of the limits
  for (int i = 0; i < limit_names.size(); i++) {
    // get the current limit values
    success = getrlimit(limit_names[i], &current_rl);
    assert (success == 0);

    // decide on the new limits
    rlim_t new_limit = get_the_limit(program_name,limit_names[i],test_case_limits, assignment_limits);
    assert (new_limit >= (rlim_t)0 && new_limit <= RLIM_INFINITY);

    // only change a value to decrease / restrict the process
    if (current_rl.rlim_max > new_limit) {
      //std::cout << "   SET LIMIT FOR " << rlimit_name_decoder(limit_names[i]) << " to " << new_limit << std::endl;
      new_rl.rlim_cur = new_rl.rlim_max = new_limit;
      success = setrlimit(limit_names[i], &new_rl);
      assert (success == 0);
    } else {
      // otherwise print a warning
      /*
        std::cout << " WARNING: no change for limit " << rlimit_name_decoder(limit_names[i]) 
        << " requested " << new_limit
        << " but keeping " << current_rl.rlim_max << std::endl;
      */
    }
  }

}


// =====================================================================================
// =====================================================================================
