/* Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license.
*/

#ifndef __HWTEMPLATE_H__
#define __HWTEMPLATE_H__

#include "TestCase.h"
//#include "GradingRubric.h"


const int hw_num = 0;
const std::string hw_name = "TestHW";

// Submission parameters
const int max_submissions = 20;
const int submission_penalty = 5;

// Compile-time parameters
const int max_clocktime = 2;		// in seconds
const int max_cputime = 2;			// in seconds
const int max_output_size = 100;	// in KB
	// OTHERS?

// Grading parameters
const int auto_pts = 30;
const int readme_pts = 2;
const int compile_pts = 3;
const int ta_pts = 20;

// File directories

// directory containing input files
const std::string input_files_dir = "../CSCI1200/testingInput";
// directory containing README and student's code
const std::string student_submit_dir = "../CSCI1200/HW0/alice/1";
// directory containing output files generated from student's code
const std::string student_output_dir = "../CSCI1200/HW0/alice/1/submit_out";
// directory containing expected output files
const std::string expected_output_dir = "../CSCI1200/Scripts/expectedOutput/HW0";
// directory to store results from validation
const std::string results_dir = "../CSCI1200/HW0/alice/1/submit_grade";

// Test cases
const int num_testcases = 3;

TestCase testcases[3] {

/************* README AND COMPILATION *****************/
TestCase(
  	"Readme",
  	"",
    "",
    "README.txt",
    "",
    "",
    2,				// points for readme
    false,
    DONT_CHECK,
    DONT_CHECK,
    NULL
),
TestCase(
	"Compilation",
	"",
	"",
	"hw0.exe",		// name of .exe created by student
	"",
	"",
	3,				// points for compilation
	false,
	DONT_CHECK,
	DONT_CHECK,
	NULL
),

/******************** TEST CASES **********************/
TestCase(
	"Case 1",							// title
	"./case1.exe",						// details
	"./a.out 1> cout.txt 2> cerr.txt",	// command
	"test1_out.txt",					// output file name
	"test1_out.txt",					// output file description
	"expected_test1.txt",				// expected output file
	5,									// points
	false,								// hidden
	WARN_IF_NOT_EMPTY,					// check cout? [DONT_CHECK, WARN_IF_NOT_EMPTY, CHECK]
	WARN_IF_NOT_EMPTY,					// check cerr? [DONT_CHECK, WARN_IF_NOT_EMPTY, CHECK]
	NULL								// compare function
)
};

#endif

