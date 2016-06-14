#ifndef __CONFIG_H__
#define __CONFIG_H__

// ===================================================================================

// Grading parameters
#define TOTAL_POINTS 10
#define AUTO_POINTS 10
#define TA_POINTS 0
#define EXTRA_CREDIT_POINTS 0


#define RLIMIT_NPROC_VALUE              20 // allow 20 additional processes  


#define ALLOW_SYSTEM_CALL_CATEGORY_COMMUNICATIONS_AND_NETWORKING_INTERPROCESS_COMMUNICATION
#define ALLOW_SYSTEM_CALL_CATEGORY_PROCESS_CONTROL_NEW_PROCESS_THREAD




// ===================================================================================
std::vector<TestCase> testcases
{
    /************* README AND COMPILATION *****************/

    TestCase::MakeCompilation(
    "Compilation",
    "/usr/bin/gcc -Wall -o a.out part1/*.c",
    "a.out",		// name of .exe created by student
    TestCasePoints(5)
    ),

    /******************** TEST CASES **********************/
    TestCase::MakeTestCase
      (
       "(+ 5 6 (* 7 -8))",
       "./a.out simple.txt",
       "./a.out simple.txt",
       TestCasePoints(5),                                      // points=0, hidden=false, extra_credit=false, view_test_case=true,  view_points=false
       { new TestCaseComparison(&myersDiffbyLinebyChar,		    // compare function [V]
                               "STDOUT.txt",
                               "STDOUT.txt",					                    // output file description
                               "simple_out.txt",
                               1
                               ),
            new TestCaseComparison(&warnIfNotEmpty,"STDERR.txt","STDERR","", 0)
           })
      
      };


#endif
