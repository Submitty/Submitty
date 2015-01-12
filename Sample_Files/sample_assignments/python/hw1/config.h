/* Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm, Sam Seng

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license.
*/

#ifndef __CONFIG_H__
#define __CONFIG_H__

#include "grading/TestCase.h"

const std::string assignment_message = "";

// Submission parameters
const int max_submissions = 20;
const int submission_penalty = 5;

// Compile-time parameters
const int max_clocktime = 10;		// in seconds
const int max_cputime = 10;			// in seconds
const int max_submission_size = 100000;	// in KB
const int max_output_size = 100000;	// in KB

// Grading parameters
const int total_pts = 0;
const int auto_pts = 0;
const int ta_pts = 25;
const int extra_credit_pts = 0;

// Test cases
const int num_testcases = 1;

// UI Interface
const bool view_points = true;
const bool view_hidden_points = false;

TestCase testcases[num_testcases] = {

    /************* README AND COMPILATION *****************/


    /******************** TEST CASES **********************/
    TestCase::MakeTestCase(
        "Part 1 Exercise Statistics",                           // title
        "python part1.py",                                           // details
        "python *.py",	                                            // command
        TestCasePoints(3),                                      // points=0, hidden=false, extra_credit=false, view_test_case=true,  view_points=false
        new TestCaseComparison(&myersDiffbyLinebyChar,		    // compare function [V]
            "output.txt",					                    // output file name [V]
            "output.txt",					                    // output file description
            "example1.txt",                        // expected output file [V]
            0
            ),
        new TestCaseComparison(&warnIfNotEmpty,"cout.txt","STDOUT","", 0),
        new TestCaseComparison(&warnIfNotEmpty,"cerr.txt","STDERR","", 0)
        )
};


#endif
