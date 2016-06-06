#ifndef __CONFIG_H__
#define __CONFIG_H__

// ===================================================================================

#define ASSIGNMENT_MESSAGE "Test Assignment"

// Grading parameters
#define TOTAL_POINTS 10
#define AUTO_POINTS 10
#define TA_POINTS 0
#define EXTRA_CREDIT_POINTS 0

// ===================================================================================

// Test cases
std::vector<TestCase> testcases
{

/******************** TEST CASES **********************/
TestCase::MakeTestCase(
	 "hello world",                                      // title
	 "details",                                          // details
	 "./a.out <nonleapyear.txt",                         // command
	 TestCasePoints(10),
	 {new TestCaseComparison(&myersDiffbyLinebyChar,     // compare function
				 "STDOUT.txt",		     // output file name
				 "Standard OUTPUT (STDOUT)", // output file description
				 "test1_output.txt",1.0) }
                       )

};


#endif
