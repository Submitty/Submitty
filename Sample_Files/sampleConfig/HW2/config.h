/* Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm, Sam Seng

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license.
*/

#ifndef __CONFIG_H__
#define __CONFIG_H__

#include <sstream>

//#include "../validation/TestCase.h"
//#include "GradingRubric.h"

const std::string id = "HW1";
const std::string name = "Text Justification";
const std::string due_date = "2014-05-15 11:59:59.0";

// Compile Command (executed in student FILES directory)
const std::string compile_command = "g++ -g *.cpp";

// Submission parameters
const int max_submissions = 20;
const int submission_penalty = 5;

// Compile-time parameters
const int max_clocktime = 2;		// in seconds
const int max_cputime = 2;			// in seconds
const int max_output_size = 100;	// in KB
	// OTHERS?

// Grading parameters
const int total_pts = 50;
const int auto_pts = 30;
const int readme_pts = 2;
const int compile_pts = 3;
const int ta_pts = 20;

// File directories

// root directory
const char* root_dir = "../CSCI1200/";
// homework directory
const char* hw_dir = "HW1/";
// input files directory
const char* input_dir = "../CSCI1200/testingInput/HW1";
// expected output files directory
const char* expected_out_dir = "../CSCI1200/Scripts/expectedOutput/HW1/";

/*
// directory containing input files
const std::string input_files_dir = "../CSCI1200/testingInput";
// directory containing README and student's code
const std::string student_submit_dir = "../CSCI1200/HW0/alice/1";
// directory containing output files generated from student's code
const std::string student_output_dir = "../CSCI1200/HW0/alice/1/.submit.out";
// directory containing expected output files
const std::string expected_output_dir = "../CSCI1200/Scripts/expectedOutput/HW0";
// directory to store results from validation
const std::string results_dir = "../CSCI1200/HW0/alice/1/.submit.grade";
*/

/************* README AND COMPILATION *****************/
TestCase readmeTestCase(
	"Readme",
	"",
	"",
	"README.txt",
	"",
	"",
	readme_pts,				// points for readme
	false,
	false,
	DONT_CHECK,
	DONT_CHECK,
	false,
	"",
	NULL
);
TestCase compilationTestCase(
	"Compilation",
	"",
	"",
	"hw0.exe",		// name of .exe created by student
	"",
	"",
	compile_pts,				// points for compilation
	false,
	false,
	DONT_CHECK,
	DONT_CHECK,
	false,
	"",
	NULL
);

// Test cases
const int num_testcases = 1;

TestCase testcases[9] {

/******************** TEST CASES **********************/
TestCase(
	"left justify example",							// title
	"./justify.exe example.txt output.txt 16 flush_left",						// details
	"./a.out",	// command
	"test1_out.txt",					// output file name [V]
	"test1_out.txt",					// output file description
	"expected_test1.txt",				// expected output file [V]
	25,									// points [V]
	false,								// hidden [V]
	false,								// extra credit [V]
	WARN_IF_NOT_EMPTY,					// check cout? [DONT_CHECK, WARN_IF_NOT_EMPTY, CHECK] [V]
	WARN_IF_NOT_EMPTY,					// check cerr? [DONT_CHECK, WARN_IF_NOT_EMPTY, CHECK] [V]
	false, 								// recompilation
	"",									// recompilation command
	&diffLine							// compare function [V]
),
TestCase(
	"Case 1",							// title
	"./case1.exe",						// details
	"./a.out",	// command
	"test1_out.txt",					// output file name [V]
	"test1_out.txt",					// output file description
	"expected_test1.txt",				// expected output file [V]
	25,									// points [V]
	false,								// hidden [V]
	false,								// extra credit [V]
	WARN_IF_NOT_EMPTY,					// check cout? [DONT_CHECK, WARN_IF_NOT_EMPTY, CHECK] [V]
	WARN_IF_NOT_EMPTY,					// check cerr? [DONT_CHECK, WARN_IF_NOT_EMPTY, CHECK] [V]
	false, 								// recompilation
	"",									// recompilation command
	&diffLine							// compare function [V]
),
TestCase(
	"Case 1",							// title
	"./case1.exe",						// details
	"./a.out",	// command
	"test1_out.txt",					// output file name [V]
	"test1_out.txt",					// output file description
	"expected_test1.txt",				// expected output file [V]
	25,									// points [V]
	false,								// hidden [V]
	false,								// extra credit [V]
	WARN_IF_NOT_EMPTY,					// check cout? [DONT_CHECK, WARN_IF_NOT_EMPTY, CHECK] [V]
	WARN_IF_NOT_EMPTY,					// check cerr? [DONT_CHECK, WARN_IF_NOT_EMPTY, CHECK] [V]
	false, 								// recompilation
	"",									// recompilation command
	&diffLine							// compare function [V]
),
TestCase(
	"Case 1",							// title
	"./case1.exe",						// details
	"./a.out",	// command
	"test1_out.txt",					// output file name [V]
	"test1_out.txt",					// output file description
	"expected_test1.txt",				// expected output file [V]
	25,									// points [V]
	false,								// hidden [V]
	false,								// extra credit [V]
	WARN_IF_NOT_EMPTY,					// check cout? [DONT_CHECK, WARN_IF_NOT_EMPTY, CHECK] [V]
	WARN_IF_NOT_EMPTY,					// check cerr? [DONT_CHECK, WARN_IF_NOT_EMPTY, CHECK] [V]
	false, 								// recompilation
	"",									// recompilation command
	&diffLine							// compare function [V]
),
TestCase(
	"Case 1",							// title
	"./case1.exe",						// details
	"./a.out",	// command
	"test1_out.txt",					// output file name [V]
	"test1_out.txt",					// output file description
	"expected_test1.txt",				// expected output file [V]
	25,									// points [V]
	false,								// hidden [V]
	false,								// extra credit [V]
	WARN_IF_NOT_EMPTY,					// check cout? [DONT_CHECK, WARN_IF_NOT_EMPTY, CHECK] [V]
	WARN_IF_NOT_EMPTY,					// check cerr? [DONT_CHECK, WARN_IF_NOT_EMPTY, CHECK] [V]
	false, 								// recompilation
	"",									// recompilation command
	&diffLine							// compare function [V]
),
TestCase(
	"Case 1",							// title
	"./case1.exe",						// details
	"./a.out",	// command
	"test1_out.txt",					// output file name [V]
	"test1_out.txt",					// output file description
	"expected_test1.txt",				// expected output file [V]
	25,									// points [V]
	false,								// hidden [V]
	false,								// extra credit [V]
	WARN_IF_NOT_EMPTY,					// check cout? [DONT_CHECK, WARN_IF_NOT_EMPTY, CHECK] [V]
	WARN_IF_NOT_EMPTY,					// check cerr? [DONT_CHECK, WARN_IF_NOT_EMPTY, CHECK] [V]
	false, 								// recompilation
	"",									// recompilation command
	&diffLine							// compare function [V]
)
};

#endif
