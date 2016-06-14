#ifndef __CONFIG_H__
#define __CONFIG_H__

// ===================================================================================

#define ASSIGNMENT_MESSAGE "The homework submission area & autograding points for Lab 1 are just practice.<br>The only grades for Lab 1 are the 3 checkpoints recorded by your TA & mentors."

// Grading parameters
#define TOTAL_POINTS 20
#define AUTO_POINTS 20
#define TA_POINTS 0
#define EXTRA_CREDIT_POINTS 2

// ===================================================================================

// Test cases
std::vector<TestCase> testcases
{
/************* README AND COMPILATION *****************/

  TestCase::MakeFileExists(
	"README",
	"part1/README.txt",
	TestCasePoints(2)
),
  TestCase::MakeCompilation(
	"Compilation",
	"/usr/bin/clang++ -Wall -o a.out -- part2/*.cpp",
	"a.out",
	TestCasePoints(3)
),

/******************** TEST CASES **********************/
TestCase::MakeTestCase(
	 "non leap year",  // title
	 "3 1 2013",                  // details
	 "./a.out <nonleapyear.txt", // 1> STDOUT.txt 2> STDERR.txt",                  // command
	 TestCasePoints(5),
	 {new TestCaseComparison(&myersDiffbyLinebyChar,				// compare function [V]			   ),
				 "STDOUT.txt",					// output file name [V]
				 "Standard OUTPUT (STDOUT)",					// output file description
				 "test1_output.txt",1.0),  // expected output
	     new TestCaseComparison(&warnIfNotEmpty,"STDERR.txt","Standard ERROR (STDERR)","",0.0)}
		       ),
TestCase::MakeTestCase(
	 "leap year",  // title
	 "3 1 2012",                  // details
	 "./a.out <leapyear.txt", // 1> STDOUT.txt 2> STDERR.txt",                  // command
	 TestCasePoints(5),
	 {new TestCaseComparison(&myersDiffbyLinebyChar,				// compare function [V]			   ),
			    "STDOUT.txt",					// output file name [V]
			    "Standard OUTPUT (STDOUT)",					// output file description
				"test2_output.txt",1.0),  // expected output
	     new TestCaseComparison(&warnIfNotEmpty,"STDERR.txt","Standard ERROR (STDERR)","",0.0)}
		       ),
TestCase::MakeTestCase(
	 "corner case A",  // title
	 "1 1 2012",                  // details
	 "./a.out <corner_case_A.txt", // 1> STDOUT.txt 2> STDERR.txt",                  // command
	 TestCasePoints(2),
	 {new TestCaseComparison(&myersDiffbyLinebyChar,				// compare function [V]			   ),
				"STDOUT.txt",					// output file name [V]
			    "Standard OUTPUT (STDOUT)",					// output file description
				"test3_output.txt",1.0),  // expected output
	     new TestCaseComparison(&warnIfNotEmpty,"STDERR.txt","Standard ERROR (STDERR)","",0.0)}
		       ),
TestCase::MakeTestCase(
	 "corner case B",  // title
	 "12 31 2012",                  // details
	 "./a.out <corner_case_B.txt", // 1> STDOUT.txt 2> STDERR.txt",                  // command
	 TestCasePoints(2),
	 {new TestCaseComparison(&myersDiffbyLinebyChar,				// compare function [V]			   ),
			    "STDOUT.txt",					// output file name [V]
			    "Standard OUTPUT (STDOUT)",					// output file description
				"test4_output.txt",1.0),  // expected output
	     new TestCaseComparison(&warnIfNotEmpty,"STDERR.txt","Standard ERROR (STDERR)","",0.0)}
		       ),
TestCase::MakeTestCase(
	 "corner case C",  // title
	 "12 31 2011",                  // details
	 "./a.out <corner_case_C.txt", // 1> STDOUT.txt 2> STDERR.txt",                  // command
	 TestCasePoints(1),
	 {new TestCaseComparison(&myersDiffbyLinebyChar,				// compare function [V]			   ),
			    "STDOUT.txt",					// output file name [V]
			    "Standard OUTPUT (STDOUT)",					// output file description
				"test5_output.txt",1.0),  // expected output
	     new TestCaseComparison(&warnIfNotEmpty,"STDERR.txt","Standard ERROR (STDERR)","",0.0)}
		       ),
TestCase::MakeTestCase(
	 "error case A",  // title
	 "13 1 2012",                  // details
	 "./a.out <error_case_A.txt", // 1> STDOUT.txt 2> STDERR.txt",                  // command
	 TestCasePoints(1,false,true),	  // non hidden, extra credit
	 {new TestCaseComparison(&myersDiffbyLinebyChar,				// compare function [V]			   ),
			    "STDOUT.txt",					// output file name [V]
			    "Standard OUTPUT (STDOUT)",					// output file description
				"test6_output.txt",0.5),  // expected output
	 new TestCaseComparison(&myersDiffbyLinebyChar,				// compare function [V]			   ),
			    "STDERR.txt",					// output file name [V]
			    "Standard ERROR (STDERR)",					// output file description
				"test6_outputB.txt",0.5)}  // expected output
		       ),
TestCase::MakeTestCase(
	 "error case B",  // title
	 "2 30 2008",                  // details
	 "./a.out <error_case_B.txt", // 1> STDOUT.txt 2> STDERR.txt",                  // command
	 TestCasePoints(1,false,true),	// non hidden, extra credit
	 {new TestCaseComparison(&myersDiffbyLinebyChar,				// compare function [V]			   ),
			    "STDOUT.txt",					// output file name [V]
			    "Standard OUTPUT (STDOUT)",					// output file description
				"test7_output.txt",0.5),  // expected output
	 new TestCaseComparison(&myersDiffbyLinebyChar,				// compare function [V]			   ),
			    "STDERR.txt",					// output file name [V]
			    "Standard ERROR (STDERR)",					// output file description
				"test7_outputB.txt",0.5)}  // expected output
		       )
};


#endif
