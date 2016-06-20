#ifndef __DEFAULT_CONFIG_H__
#define __DEFAULT_CONFIG_H__

#include "TestCase.h"
#include "config.h"

// ========================================================================================

#ifndef ASSIGNMENT_MESSAGE
#define ASSIGNMENT_MESSAGE ""
#endif

// ========================================================================================

#ifndef MAX_NUM_SUBMISSIONS
#define MAX_NUM_SUBMISSIONS 20
#endif
#ifndef SUBMISSION_PENALTY
#define SUBMISSION_PENALTY 5
#endif
#ifndef MAX_SUBMISSION_SIZE
#define MAX_SUBMISSION_SIZE 100000      // 100 KB submitted files size
#endif
#ifndef PART_NAMES
#define PART_NAMES { }
#endif

// ========================================================================================

#ifndef AUTO_POINTS 
#define AUTO_POINTS 0
#endif
#ifndef TA_POINTS 
#define TA_POINTS 0
#endif
#ifndef TOTAL_POINTS 
#define TOTAL_POINTS (AUTO_POINTS+TA_POINTS)
#endif
#ifndef EXTRA_CREDIT_POINTS 
#define EXTRA_CREDIT_POINTS 0
#endif

// ========================================================================================

#ifndef RLIMIT_CPU_VALUE
#define RLIMIT_CPU_VALUE        10                // 10 seconds per test
#endif
#ifndef RLIMIT_FSIZE_VALUE
#define RLIMIT_FSIZE_VALUE      100*1000          // 100 KB created file size
#endif
#ifndef RLIMIT_DATA_VALUE
#define RLIMIT_DATA_VALUE       500*1000*1000     // 500 MB heap
#endif
#ifndef RLIMIT_STACK_VALUE
#define RLIMIT_STACK_VALUE      500*1000*1000     // 500 MB stack   // FIXME: could make smaller, 1 MB per thread 
#endif
#ifndef RLIMIT_CORE_VALUE
#define RLIMIT_CORE_VALUE       0                 // don't allow core files 
#endif
#ifndef RLIMIT_RSS_VALUE
#define RLIMIT_RSS_VALUE        RLIM_INFINITY     //                   // FIXME: deprecated, set to 0? 
#endif
#ifndef RLIMIT_NPROC_VALUE
#define RLIMIT_NPROC_VALUE      0                 // no additional processes  
#endif
#ifndef RLIMIT_NOFILE_VALUE
#define RLIMIT_NOFILE_VALUE     100               // 100 file descriptors 

#endif
#ifndef RLIMIT_MEMLOCK_VALUE
#define RLIMIT_MEMLOCK_VALUE    500*1000*1000     // 500 MB RAM             // FIXME: set to 0
#endif
#ifndef RLIMIT_AS_VALUE
#define RLIMIT_AS_VALUE         1*1000*1000*1000  // 1 GB virtual memory address space 
#endif
#ifndef RLIMIT_LOCKS_VALUE
#define RLIMIT_LOCKS_VALUE      100               // 100 files open  // FIXME: set to 0 (except java?) 
#endif
#ifndef RLIMIT_SIGPENDING_VALUE
#define RLIMIT_SIGPENDING_VALUE 0                 // 
#endif
#ifndef RLIMIT_MSGQUEUE_VALUE
#define RLIMIT_MSGQUEUE_VALUE   0                 //  
#endif
#ifndef RLIMIT_NICE_VALUE
#define RLIMIT_NICE_VALUE       RLIM_INFINITY     // 
#endif
#ifndef RLIMIT_RTPRIO_VALUE
#define RLIMIT_RTPRIO_VALUE     0                 // 
#endif
#ifndef RLIMIT_RTTIME_VALUE
#define RLIMIT_RTTIME_VALUE     0                 // 
#endif


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
