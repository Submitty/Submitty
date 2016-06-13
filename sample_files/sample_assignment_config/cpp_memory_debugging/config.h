#ifndef __CONFIG_H__
#define __CONFIG_H__

// ===================================================================================

#define TOTAL_POINTS 14
#define AUTO_POINTS 14

// drmemory_path is defined in TestCase.h
std::string drmemory_flags = " -m32 -g ";

#define ASSIGNMENT_MESSAGE "The homework submission area & autograding points for Lab are just practice.<br>The only grades for Lab are the 3 checkpoints recorded by your TA & mentors."

// ===================================================================================

std::vector<TestCase> testcases
{

/************* README AND COMPILATION *****************/

  TestCase::MakeCompilation
  (
   "Compilation of Submitted Files (for Dr. Memory): g++ -m32 -g -Wall *cpp -o submitted_32.out",
   "/usr/bin/clang++ " + drmemory_flags + " -Wall -o submitted_32.out -- *.cpp",
   "submitted_32.out",	
   TestCasePoints(2)
   ),

  TestCase::MakeCompilation
  (
   "Compilation of Submitted Files (for Valgrind): g++ -g -Wall *cpp -o submitted.out",
   "/usr/bin/clang++ -g -Wall -o submitted.out -- *.cpp",
   "submitted.out",
   TestCasePoints(2)
   ),

/******************** TEST CASES **********************/


TestCase::MakeTestCase
  (
   "Under Dr Memory",
   "drmemory -brief -- ./submitted_32.out",
   drmemory_path + " -brief -- ./submitted_32.out",
   TestCasePoints(5),
   {new TestCaseComparison(&warnIfEmpty,"STDOUT.txt","STDOUT"),
       new TestCaseTokens(&searchToken,"STDERR.txt","STDERR", std::vector<std::string>(1,std::string("NO ERRORS FOUND:")))}
   ),

TestCase::MakeTestCase
  (
   "Under Valgrind",
   "valgrind --leak-check=full ./submitted.out",
   "/usr/bin/valgrind --leak-check=full ./submitted.out",
   TestCasePoints(5),
   {new TestCaseComparison(&warnIfEmpty,"STDOUT.txt","STDOUT"),
       new TestCaseTokens(&searchToken,"STDERR.txt","STDERR", std::vector<std::string>(1,std::string("ERROR SUMMARY: 0 errors from 0 contexts")))
       }
   )

};


#endif
