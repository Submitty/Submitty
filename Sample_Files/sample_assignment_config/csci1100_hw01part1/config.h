#ifndef __CONFIG_H__
#define __CONFIG_H__

#include "grading/TestCase.h"

const std::string assignment_message = "";


// Submission parameters
const int max_submissions = 20;
const int submission_penalty = 5;


// Compile-time parameters
const int max_cputime = 2;		// in seconds, per test case
const int max_submission_size = 10000;	// in KB, entire submission (could be zip file)
const int max_output_size =     100;	// in KB, per created output file



// Grading parameters
const int total_pts = 0;
const int auto_pts = 0;
const int ta_pts = 0;
const int extra_credit_pts = 0;


//std::vector<std::string> testhere{"hi","bye"};

//std::vector<TestCase> testcases 

const int num_testcases=1;
TestCase testcases[num_testcases] =
  {
    
    /******************** TEST CASES **********************/
     TestCase::MakeTestCase
    ("Lab 1 Checkpoint 1",       //  title
     "python *.py",              //  command (seen by students)
     "/usr/bin/python *.py",     //  actual command (full path req'd)
     TestCasePoints(0),          //  no points/grading awarded
     new TestCaseComparison
     (&myersDiffbyLinebyChar,    //  comparison function
      "STDOUT.txt",	         //  output file name 
      "Program Output",	         //  label for output file
      "hw01part1_sol.txt"),      //  comparison file
     new TestCaseComparison(&warnIfNotEmpty,"STDERR.txt","syntax error output from running python")
     )
    
  };

#endif
