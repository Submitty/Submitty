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
const int total_pts = 0;
const int auto_pts = 0;
const int ta_pts = 0;
const int extra_credit_pts = 0;

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
		"Compilation".
		"javac -cp /HWserver/Sample_Files/sample_assignment_config/csci2600/junit-4.12.jar *.java"
	),

	/******** TEST CASES ******************************/
	//using the TestClassTester provided in .zip file from GitHub
	TestCase::MakeTestCase(
		"example",
		"java -cp /HWserver/Sample_Files/sample_assignment_config/csci2600/junit-4.12.jar:/HWserver/Sample_Files/sample_assignment_config/csci2600/hamcrest-core-1.3.jar:. org.junit.runner.JUnitCore TestClassTester",
		TestCasePoints(0),

		new TestCaseComparison(
								&myersDiff,
								"STDOUT.txt"
								"Program Output",
								"output_1.txt"),
		new TestCaseComparison(&warnIfNotEmpty, "STDERR.txt", "syntax error output from running junit")
	)
};

#endif
