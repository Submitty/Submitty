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

const std::string junit_jar_path = "/local/scratch0/submit3/bin/junit-4.12.jar";

//Test cases
std::vector<TestCase> testcases
{
	/******* README AND COMPILATION **********************/

	TestCase::MakeFileExists(
		"README",
		"README.txt",
		TestCasePoints(2)
	),

	//path to .jar will change
	TestCase::MakeCompilation(
				  "Compilation",
				  "/usr/bin/javac -cp "+junit_jar_path+" *.java",
				  "foo.class",
				  TestCasePoints(2)
	),

	/******** TEST CASES ******************************/
	//using the TestClassTester provided in .zip file from GitHub
	TestCase::MakeTestCase(
			       "Run junit tests",
			       "could put details for user here",
		//		"/usr/bin/java -cp /HWserver/Sample_Files/sample_assignment_config/csci2600/junit-4.12.jar:/HWserver/Sample_Files/sample_assignment_config/csci2600/hamcrest-core-1.3.jar:. org.junit.runner.JUnitCore TestClassTester",
		"/usr/bin/java -cp "+junit_jar_path+":/HWserver/Sample_Files/sample_assignment_config/csci2600/hamcrest-core-1.3.jar:. org.junit.runner.JUnitCore TestClassTester",
		TestCasePoints(2),

		new TestCaseComparison(
								&myersDiffbyLinebyChar,
								"STDOUT.txt"
								"Program Output",
								"output_1.txt"),
		new TestCaseComparison(&warnIfNotEmpty, "STDERR.txt", "syntax error output from running junit")
	)
};

#endif
