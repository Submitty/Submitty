/* Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm, Sam Seng

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license.
*/


#ifndef __CONFIG_H__
#define __CONFIG_H__

#include "grading/TestCase.h"

const std::string assignment_message = "The homework submission area & autograding points for Lab 1 are just practice.<br>The only grades for Lab 1 are the 3 checkpoints recorded by your TA & mentors.";

// Submission parameters
const int max_submissions = 20;
const int submission_penalty = 5;

// Compile-time parameters
const int max_clocktime = 200; // = 2;		// in seconds
const int max_cputime = 200; // = 2;			// in seconds
const int max_submission_size = 10000;	// in KB
const int max_output_size = 10000;	// in KB
	// OTHERS?

// Grading parameters
const int total_pts = 20;
const int auto_pts = 20;
const int ta_pts = 0;
const int extra_credit_pts = 2;

// Test cases
std::vector<TestCase> testcases
{
/************* README AND COMPILATION *****************/

  TestCase::MakeFileExists(
	"README",
	"README.txt",
	TestCasePoints(2)
),
  TestCase::MakeCompilation(
	"Compilation",
	"/usr/bin/clang++ -Wall -o a.out -- *.cpp",
	"a.out",		// name of .exe created by student
	TestCasePoints(3)
),

/******************** TEST CASES **********************/
TestCase::MakeTestCase(
	 "non leap year",  // title
	 "3 1 2013",                  // details
	 "./a.out <nonleapyear.txt", // 1> STDOUT.txt 2> STDERR.txt",                  // command
	 TestCasePoints(5),
	 new TestCaseComparison(&myersDiffbyLinebyChar,				// compare function [V]			   ),
				"STDOUT.txt",					// output file name [V]
				"Standard OUTPUT (STDOUT)",					// output file description
				"test1_output.txt",1.0),  // expected output
	 new TestCaseComparison(&warnIfNotEmpty,"STDERR.txt","Standard ERROR (STDERR)","",0.0)
		       ),
TestCase::MakeTestCase(
	 "leap year",  // title
	 "3 1 2012",                  // details
	 "./a.out <leapyear.txt", // 1> STDOUT.txt 2> STDERR.txt",                  // command
	 TestCasePoints(5),
	 new TestCaseComparison(&myersDiffbyLinebyChar,				// compare function [V]			   ),
			    "STDOUT.txt",					// output file name [V]
			    "Standard OUTPUT (STDOUT)",					// output file description
				"test2_output.txt",1.0),  // expected output
	 new TestCaseComparison(&warnIfNotEmpty,"STDERR.txt","Standard ERROR (STDERR)","",0.0)
		       ),
TestCase::MakeTestCase(
	 "corner case A",  // title
	 "1 1 2012",                  // details
	 "./a.out <corner_case_A.txt", // 1> STDOUT.txt 2> STDERR.txt",                  // command
	 TestCasePoints(2),
	 new TestCaseComparison(&myersDiffbyLinebyChar,				// compare function [V]			   ),
			    "STDOUT.txt",					// output file name [V]
			    "Standard OUTPUT (STDOUT)",					// output file description
				"test3_output.txt",1.0),  // expected output
	 new TestCaseComparison(&warnIfNotEmpty,"STDERR.txt","Standard ERROR (STDERR)","",0.0)
		       ),
TestCase::MakeTestCase(
	 "corner case B",  // title
	 "12 31 2012",                  // details
	 "./a.out <corner_case_B.txt", // 1> STDOUT.txt 2> STDERR.txt",                  // command
	 TestCasePoints(2),
	 new TestCaseComparison(&myersDiffbyLinebyChar,				// compare function [V]			   ),
			    "STDOUT.txt",					// output file name [V]
			    "Standard OUTPUT (STDOUT)",					// output file description
				"test4_output.txt",1.0),  // expected output
	 new TestCaseComparison(&warnIfNotEmpty,"STDERR.txt","Standard ERROR (STDERR)","",0.0)
		       ),
TestCase::MakeTestCase(
	 "corner case C",  // title
	 "12 31 2011",                  // details
	 "./a.out <corner_case_C.txt", // 1> STDOUT.txt 2> STDERR.txt",                  // command
	 TestCasePoints(1),
	 new TestCaseComparison(&myersDiffbyLinebyChar,				// compare function [V]			   ),
			    "STDOUT.txt",					// output file name [V]
			    "Standard OUTPUT (STDOUT)",					// output file description
				"test5_output.txt",1.0),  // expected output
	 new TestCaseComparison(&warnIfNotEmpty,"STDERR.txt","Standard ERROR (STDERR)","",0.0)
		       ),
TestCase::MakeTestCase(
	 "error case A",  // title
	 "13 1 2012",                  // details
	 "./a.out <error_case_A.txt", // 1> STDOUT.txt 2> STDERR.txt",                  // command
	 TestCasePoints(1,false,true),	  // non hidden, extra credit
	 new TestCaseComparison(&myersDiffbyLinebyChar,				// compare function [V]			   ),
			    "STDOUT.txt",					// output file name [V]
			    "Standard OUTPUT (STDOUT)",					// output file description
				"test6_output.txt",0.5),  // expected output
	 new TestCaseComparison(&myersDiffbyLinebyChar,				// compare function [V]			   ),
			    "STDERR.txt",					// output file name [V]
			    "Standard ERROR (STDERR)",					// output file description
				"test6_outputB.txt",0.5)  // expected output
		       ),
TestCase::MakeTestCase(
	 "error case B",  // title
	 "2 30 2008",                  // details
	 "./a.out <error_case_B.txt", // 1> STDOUT.txt 2> STDERR.txt",                  // command
	 TestCasePoints(1,false,true),	// non hidden, extra credit
	 new TestCaseComparison(&myersDiffbyLinebyChar,				// compare function [V]			   ),
			    "STDOUT.txt",					// output file name [V]
			    "Standard OUTPUT (STDOUT)",					// output file description
				"test7_output.txt",0.5),  // expected output
	 new TestCaseComparison(&myersDiffbyLinebyChar,				// compare function [V]			   ),
			    "STDERR.txt",					// output file name [V]
			    "Standard ERROR (STDERR)",					// output file description
				"test7_outputB.txt",0.5)  // expected output
		       )
};


#endif
