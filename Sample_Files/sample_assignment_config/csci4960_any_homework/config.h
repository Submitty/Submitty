#ifndef __CONFIG_H__
#define __CONFIG_H__

// ===================================================================================

#define ASSIGNMENT_MESSAGE "No automatic grading"

// Grading parameters
#define TOTAL_POINTS 1
#define AUTO_POINTS 1

// ===================================================================================

// Test cases
std::vector<TestCase> testcases
{
  TestCase::MakeFileExists
  ( "README", "README.txt", TestCasePoints(1) )
};

// ===================================================================================

#endif
