#ifndef __DEFAULT_CONFIG_H__
#define __DEFAULT_CONFIG_H__

#include "TestCase.h"

extern const char *GLOBAL_config_json_string;  // defined in json_generated.cpp

// ========================================================================================

#define ASSIGNMENT_MESSAGE ""

// ========================================================================================

#define MAX_NUM_SUBMISSIONS 20
#define SUBMISSION_PENALTY 5
#define MAX_SUBMISSION_SIZE 100000      // 100 KB submitted files size
#define PART_NAMES { }

// ========================================================================================

/*
#define AUTO_POINTS 0
#define TA_POINTS 0
#define TOTAL_POINTS (AUTO_POINTS+TA_POINTS)
#define EXTRA_CREDIT_POINTS 0
*/

// ========================================================================================

#define RLIMIT_CPU_VALUE        10                // 10 seconds per test
#define RLIMIT_FSIZE_VALUE      10*1000           // 10 KB created file size
#define RLIMIT_DATA_VALUE       500*1000*1000     // 500 MB heap
#define RLIMIT_STACK_VALUE      500*1000*1000     // 500 MB stack   // FIXME: could make smaller, 1 MB per thread 
#define RLIMIT_CORE_VALUE       0                 // don't allow core files 
#define RLIMIT_RSS_VALUE        RLIM_INFINITY     //                   // FIXME: deprecated, set to 0? 
#define RLIMIT_NPROC_VALUE      0                 // no additional processes  
#define RLIMIT_NOFILE_VALUE     100               // 100 file descriptors 

#define RLIMIT_MEMLOCK_VALUE    500*1000*1000     // 500 MB RAM             // FIXME: set to 0
#define RLIMIT_AS_VALUE         1*1000*1000*1000  // 1 GB virtual memory address space 
#define RLIMIT_LOCKS_VALUE      100               // 100 files open  // FIXME: set to 0 (except java?) 
#define RLIMIT_SIGPENDING_VALUE 0                 // 
#define RLIMIT_MSGQUEUE_VALUE   0                 //  
#define RLIMIT_NICE_VALUE       RLIM_INFINITY     // 
#define RLIMIT_RTPRIO_VALUE     0                 // 
#define RLIMIT_RTTIME_VALUE     0                 // 


// ========================================================================================
// ========================================================================================

extern const std::map<int,rlim_t> assignment_limits;
const std::map<int,rlim_t> assignment_limits = 
  { 
    { RLIMIT_CPU,        RLIMIT_CPU_VALUE        },
    { RLIMIT_FSIZE,      RLIMIT_FSIZE_VALUE      },
    { RLIMIT_DATA,       RLIMIT_DATA_VALUE       },
    { RLIMIT_STACK,      RLIMIT_STACK_VALUE      },
    { RLIMIT_CORE,       RLIMIT_CORE_VALUE       },
    { RLIMIT_RSS,        RLIMIT_RSS_VALUE        },
    { RLIMIT_NPROC,      RLIMIT_NPROC_VALUE      },
    { RLIMIT_NOFILE,     RLIMIT_NOFILE_VALUE     },
    { RLIMIT_MEMLOCK,    RLIMIT_MEMLOCK_VALUE    },
    { RLIMIT_AS,         RLIMIT_AS_VALUE         },
    { RLIMIT_LOCKS,      RLIMIT_LOCKS_VALUE      },
    { RLIMIT_SIGPENDING, RLIMIT_SIGPENDING_VALUE },
    { RLIMIT_MSGQUEUE,   RLIMIT_MSGQUEUE_VALUE   },
    { RLIMIT_NICE,       RLIMIT_NICE_VALUE       },
    { RLIMIT_RTPRIO,     RLIMIT_RTPRIO_VALUE     },
    { RLIMIT_RTTIME,     RLIMIT_RTTIME_VALUE     }
  };


// ========================================================================================
// ========================================================================================

#endif // __DEFAULT_CONFIG_H__
