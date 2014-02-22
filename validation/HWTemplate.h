/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license.
*/

#include <string>
#include <vector>

#include "TestCase.h"
//#include "GradingRubric.h"

class TestCase;

const int hw_num = 0;
const std::string hw_name = "TestHW";

// Submission parameters
const int max_submissions = 0;
const int submission_penalty = 0;
const std::string input_file_dir = "";
const std::string output_file_dir = "";

// Compile-time parameters
const int max_clocktime = 0;
const int max_cputime = 0;
const int max_output_size = 0;
	// OTHERS?

// Grading parameters
const int auto_pts = 0;
const int readme_pts = 0;
const int compile_pts = 0;
const int ta_pts = 0;

// Test cases
const int num_testcases = 1;

std::vector<TestCase> testcases;

TestCase case1;
  case1.setTitle("Case 1");
  case1.setDetails("./case1.exe");
  case1.setCommand("./a.out 1> cout.txt 2> cerr.txt");
  case1.setPoints(5);
  case1.setHidden(false);
  case1.setFilename("test_out.txt");
  case1.setDescription("test_out.txt");
  case1.setExpected("expected_test1.txt");
  case1.setCompare(&diff);
  
  /* TODO: SHOULD COUT AND CERR CHECKS ALWAYS BE INCLUDED?
            IF SO, JUST DO THESE AUTOMATICALLY IN VALIDATOR*/
  
  /*Check cout_check;
  cout_check.setFilename("cout.txt");
  cout_check.setDescription("Standard OUTPUT (STDOUT)");
  cout_check.setExpected(NULL);
  //cout_check.setCompare();		// warn if not empty?
  cout_check.setSideBySide(true);
  cout_check.setPrintCheck(WARNING_OR_FAILURE);
  
  Check cerr_check;
  cerr_check.setFilename("cerr.txt");
  cerr_check.setDescription("Standard ERROR (STDERR)");
  cerr_check.setExpected(NULL);
  //cerr_check.setCompare();		// warn if not empty?
  cerr_check.setSideBySide(true);
  cerr_check.setPrintCheck(WARNING_OR_FAILURE);*/
  
  case1.addCheck(output_check);
  case1.addCheck(cout_check);
  case1.addCheck(cerr_check);
testcases.push_back(case1);


