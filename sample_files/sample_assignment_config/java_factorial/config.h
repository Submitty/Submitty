#ifndef __CONFIG_H_
#define __CONFIG_H_

// ===================================================================================

//path to .jar will change
const std::string junit_jar_path =         "/usr/local/submitty/JUnit/junit-4.12.jar";
const std::string hamcrest_core_jar_path = "/usr/local/submitty/JUnit/hamcrest-core-1.3.jar";
const std::string emma_jar_path =          "/usr/local/submitty/JUnit/emma.jar";

// ===================================================================================

// Grading parameters
#define TOTAL_POINTS 20
#define AUTO_POINTS 20
#define TA_POINTS 0

// ===================================================================================

// assignment defaults
#define RLIMIT_AS_VALUE     RLIM_INFINITY  // unlimied address space
//#define RLIMIT_NPROC_VALUE  100            // 100 additional processes
#define  RLIMIT_CPU_VALUE  60 // RLIM_INFINITY
#define  RLIMIT_NPROC_VALUE  RLIM_INFINITY
//#define  RLIMIT_DATA_VALUE  RLIM_INFINITY


// (could be different that the assignment defaults)
const std::map<int,rlim_t> java_test_case_limits = 
  { 
    { RLIMIT_AS,         RLIM_INFINITY },  // unlimited address space
    { RLIMIT_NPROC,      100           }   // 10 additional process
  };

// ===================================================================================

//Test cases
std::vector<TestCase> testcases
{
    /******* README AND COMPILATION **********************/
   
  
   TestCase::MakeCompilation
   (
     "Compilation of student code: Factorial.java",
     "/usr/bin/javac -cp "+junit_jar_path+" Factorial.java",   
     "Factorial.class",
     TestCasePoints(2)
     //      java_test_case_limits
   ),

      

    TestCase::MakeCompilation
    (
     "Compilation of instructor test cases: *Test.java",
     "/usr/bin/javac -cp "+junit_jar_path+":. FactorialTest.java",
     "FactorialTest.class",
     TestCasePoints(2)
     // java_test_case_limits
     ),
    
      
    /******** INSTRUMENTATION FOR EMMA ******************************/    
    /* No need to instrument and measure coverage for hw0   
    TestCase::MakeTestCase
    (
     "Instrumentation of student code",
     "",
     "/usr/bin/java -cp "+emma_jar_path+" emma instr -m overwrite -ip hw0/",
     TestCasePoints(0),
     { TestCaseJUnit::EmmaInstrumentationGrader("STDOUT.txt",1.0),
	 new TestCaseComparison(&warnIfNotEmpty, "STDERR.txt", "Error output from running instrumentation","",0.0) },
     "",
     java_test_case_limits
     ),
    */


    /******** TEST CASES ******************************/

      TestCase::MakeTestCase
      (
       "FactorialTest",
       "",
       "/usr/bin/java -cp "+junit_jar_path+":"+hamcrest_core_jar_path+":"+emma_jar_path+":. org.junit.runner.JUnitCore FactorialTest",
       TestCasePoints(16),
       { TestCaseJUnit::JUnitTestGrader("STDOUT.txt",4,1.0),
	   new TestCaseComparison(&warnIfNotEmpty, "STDERR.txt", "syntax error output from running junit","",0.0) }
       )

      /*  
    TestCase::MakeTestCase
    (
     "BallTest",
     "",
     "/usr/bin/java -cp "+junit_jar_path+":"+hamcrest_core_jar_path+":"+emma_jar_path+":. org.junit.runner.JUnitCore hw0.test.BallTest",
     TestCasePoints(1),
     { TestCaseJUnit::JUnitTestGrader("STDOUT.txt",1,1.0),
	 new TestCaseComparison(&warnIfNotEmpty, "STDERR.txt", "syntax error output from running junit","",0.0) }
     ),
    TestCase::MakeTestCase
    (
     "BallContainerTest",
     "",
     "/usr/bin/java -cp "+junit_jar_path+":"+hamcrest_core_jar_path+":"+emma_jar_path+":. org.junit.runner.JUnitCore hw0.test.BallContainerTest",
     TestCasePoints(8),
     { TestCaseJUnit::JUnitTestGrader("STDOUT.txt",8,1.0),
	 new TestCaseComparison(&warnIfNotEmpty, "STDERR.txt", "syntax error output from running junit","",0.0) }
     ),
    TestCase::MakeTestCase
    (
     "BoxTest",
     "",
     "/usr/bin/java -cp "+junit_jar_path+":"+hamcrest_core_jar_path+":"+emma_jar_path+":. org.junit.runner.JUnitCore hw0.test.BoxTest",
     TestCasePoints(16),
     { TestCaseJUnit::JUnitTestGrader("STDOUT.txt",8,1.0),
	 new TestCaseComparison(&warnIfNotEmpty, "STDERR.txt", "syntax error output from running junit","",0.0) }
     ),
      */
 
    /******** Instructor TESTS ********/
   
      /*
   TestCase::MakeTestCase
    (
     "Instructor Box tests",
     "",
     "/usr/bin/java -cp "+junit_jar_path+":"+hamcrest_core_jar_path+":"+emma_jar_path+":. org.junit.runner.JUnitCore hw0.test.HiddenBoxTest",
     TestCasePoints(4,true),
     { TestCaseJUnit::JUnitTestGrader("STDOUT.txt",1,1.0),
      new TestCaseComparison(&warnIfNotEmpty, "STDERR.txt", "syntax error output from running junit","",0.0) }
    )
      */

    /******** COVERAGE ******************************/
    /* No need to measure coverage for hw0  
    TestCase::MakeTestCase
    (
     "Generate coverage report for HW0 Tests",
     "",
     "/usr/bin/java -cp "+emma_jar_path+" emma report -r txt -in coverage.em,coverage.ec -Dreport.txt.out.file=emma_report.txt",
     TestCasePoints(4),
     { new TestCaseComparison(&errorIfEmpty, "STDOUT.txt", "EclEmma report generation output","",0.0),
      TestCaseJUnit::EmmaCoverageReportGrader("emma_report.txt",80,1.0),
	 new TestCaseComparison(&warnIfNotEmpty, "STDERR.txt", "error output from running EclEmma report generation","",0.0) }
     )
    */

};

#endif
