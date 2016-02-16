#ifndef __CONFIG_H__
#define __CONFIG_H__

// ===================================================================================

#define ASSIGNMENT_MESSAGE "Test Assignment"

// Grading parameters
#define TOTAL_POINTS 20
#define AUTO_POINTS 20
#define TA_POINTS 0
#define EXTRA_CREDIT_POINTS 0

// ===================================================================================

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
	TestCasePoints(3),
	{   
	  { RLIMIT_NPROC,      10           }   // 10 additional process
	}
),

/******************** TEST CASES **********************/
TestCase::MakeTestCase(
	 "hello world",  // title
	 "details",                  // details
	 "./a.out <nonleapyear.txt", // 1> STDOUT.txt 2> STDERR.txt",                  // command
	 TestCasePoints(15),
	 {new TestCaseComparison(&myersDiffbyLinebyChar,				// compare function [V]			   ),
				 "STDOUT.txt",					// output file name [V]
				 "Standard OUTPUT (STDOUT)",					// output file description
				 "test1_output.txt",1.0),  // expected output
	     new TestCaseComparison(&warnIfNotEmpty,"STDERR.txt","Standard ERROR (STDERR)","",0.0)}
		       ),
};


#endif
