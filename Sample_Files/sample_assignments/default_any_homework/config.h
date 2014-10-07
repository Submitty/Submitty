/* Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm, Sam Seng

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license.
*/

#ifndef __CONFIG_H__
#define __CONFIG_H__

#include "grading/TestCase.h"

const std::string assignment_message = "No automatic grading";

// Submission parameters
const int max_submissions = 20;
const int submission_penalty = 5;

// Compile-time parameters
const int max_clocktime = 2;		// in seconds
const int max_cputime = 2;			// in seconds
const int max_submission_size = 100000;	// in KB
const int max_output_size = 100;	// in KB

// Grading parameters
const int total_pts = 1;
const int auto_pts = 1;
const int ta_pts = 0;
const int extra_credit_pts = 0;

// Test cases
const int num_testcases = 1;

TestCase testcases[num_testcases] = {

/************* README AND COMPILATION *****************/

  TestCase::MakeFileExists
  ( "README", "README.txt", TestCasePoints(1))

};


#endif
