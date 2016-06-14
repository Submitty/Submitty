#ifndef __CONFIG_H__
#define __CONFIG_H__

// ===================================================================================
#define ASSIGNMENT_MESSAGE "Submit each part of your homework to the right bucket or you will not receive credit."

// Grading parameters
#define TOTAL_POINTS 10
#define AUTO_POINTS 10
#define TA_POINTS 0
#define EXTRA_CREDIT_POINTS 0
std::vector<TestCase> testcases 
  {
    
    /******************** TEST CASES **********************/
     TestCase::MakeTestCase
    ("Part 1 Compute square root",       //  title
     "python *.py",              //  command (seen by students)
     "/usr/bin/python part1/*.py",     //  actual command (full path req'd)
     TestCasePoints(3),          //  no points/grading awarded
     {new TestCaseComparison
     (&myersDiffbyLinebyChar,    //  comparison function
      "STDOUT.txt",	         //  output file name 
      "Program Output",	         //  label for output file
      "part1_sol.txt", 1.0),      //  comparison file
	 new TestCaseComparison(&warnIfNotEmpty,"STDERR.txt","syntax error output from running python", "", 0.0)}
     ),
    /******************************************************/
     TestCase::MakeTestCase
    ("Part 2 Solve for x^2 + 5x + 6 = 0",       //  title
     "python *.py",              //  command (seen by students)
     "/usr/bin/python part2/*.py",     //  actual command (full path req'd)
     TestCasePoints(4),          //  no points/grading awarded
     {new TestCaseComparison
     (&myersDiffbyLinebyChar,    //  comparison function
      "STDOUT.txt",          //  output file name 
      "Program Output",          //  label for output file
      "part2_sol.txt", 1.0),      //  comparison file
   new TestCaseComparison(&warnIfNotEmpty,"STDERR.txt","syntax error output from running python", "", 0.0)}
     ),
    /******************************************************/
     TestCase::MakeTestCase
    ("Part 3 Count from 1 to 10",       //  title
     "python *.py",              //  command (seen by students)
     "/usr/bin/python part3/*.py",     //  actual command (full path req'd)
     TestCasePoints(3),          //  no points/grading awarded
     {new TestCaseComparison
     (&myersDiffbyLinebyChar,    //  comparison function
      "STDOUT.txt",          //  output file name 
      "Program Output",          //  label for output file
      "part3_sol.txt", 1.0),      //  comparison file
   new TestCaseComparison(&warnIfNotEmpty,"STDERR.txt","syntax error output from running python", "", 0.0)}
     )
    
  };

// ===================================================================================

#endif
