#ifndef __CONFIG_H_
#define __CONFIG_H_

#include "grading/TestCase.h"

const std::string assignment_message = "";

// Submission parameters
const int max_submissions = 20;
const int submission_penalty = 5;


// Compile-time parameters
const int max_cputime = 2;		// in seconds, per test case
const int max_submission_size = 10000;	// in KB, entire submission (could be zip file)
const int max_output_size =     1000;	// in KB, per created output file



// Grading parameters
const int total_pts = 6;
const int auto_pts = 6;
const int ta_pts = 0;
const int extra_credit_pts = 0;

//path to .jar will change
const std::string junit_jar_path =         "/usr/local/hss/JUnit/junit-4.12.jar";
const std::string hamcrest_core_jar_path = "/usr/local/hss/JUnit/hamcrest-core-1.3.jar";

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
	   TestCaseJUnit::JUnitTestGrader("STDOUT.txt",1),
	   new TestCaseComparison(&warnIfNotEmpty, "STDERR.txt", "syntax error output from running junit")
	   )
};

#endif
