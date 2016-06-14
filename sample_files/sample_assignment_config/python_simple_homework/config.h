#ifndef __CONFIG_H__
#define __CONFIG_H__

// ===================================================================================

std::vector<TestCase> testcases 
  {
    
    /******************** TEST CASES **********************/
     TestCase::MakeTestCase
    ("Lab 1 Checkpoint 1",       //  title
     "python *.py",              //  command (seen by students)
     "/usr/bin/python part1/*.py",     //  actual command (full path req'd)
     TestCasePoints(0),          //  no points/grading awarded
     {new TestCaseComparison
     (&myersDiffbyLinebyChar,    //  comparison function
      "STDOUT.txt",	         //  output file name 
      "Program Output",	         //  label for output file
      "hw01part1_sol.txt"),      //  comparison file
	 new TestCaseComparison(&warnIfNotEmpty,"STDERR.txt","syntax error output from running python")}
     )
    
  };

// ===================================================================================

#endif
