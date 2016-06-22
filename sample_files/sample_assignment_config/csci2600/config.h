#ifndef __CONFIG_H_
#define __CONFIG_H_

#include "grading/TestCase.h"

// ===================================================================================

// Grading parameters
#define TOTAL_POINTS 6
#define AUTO_POINTS 6

// ===================================================================================

//path to .jar will change
const std::string junit_jar_path =         "/usr/local/submitty/JUnit/junit-4.12.jar";
const std::string hamcrest_core_jar_path = "/usr/local/submitty/JUnit/hamcrest-core-1.3.jar";
const std::string emma_jar_path =          "/usr/local/submitty/JUnit/emma.jar";

// ===================================================================================


#define RLIMIT_AS_VALUE RLIM_INFINITY
#define RLIMIT_NPROC_VALUE 100


// java & javac need unlimited heap & address space
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

	TestCase::MakeCompilation(
				  "Compilation of student code",
				  "/usr/bin/javac -cp "+junit_jar_path+" TestClass.java",
				  "TestClass.class",
				  TestCasePoints(2)
	),

	TestCase::MakeCompilation(
				  "Compilation of test cases",
				  "/usr/bin/javac -cp "+junit_jar_path+":. TestClassTester.java",
				  "TestClassTester.class",
				  TestCasePoints(2)
	),

	/******** TEST CASES ******************************/
	TestCase::MakeTestCase
	  ("Run junit tests",
	   "", /* could put more details for the user here */
	   "/usr/bin/java -cp "+junit_jar_path+":"+hamcrest_core_jar_path+":. org.junit.runner.JUnitCore TestClassTester",
	   TestCasePoints(2),
	   {TestCaseJUnit::JUnitTestGrader("STDOUT.txt",1),
	       new TestCaseComparison(&warnIfNotEmpty, "STDERR.txt", "syntax error output from running junit")}
	   )
};

// ===================================================================================

#endif
