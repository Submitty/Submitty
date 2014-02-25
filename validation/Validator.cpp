/* Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license.
*/

#include <iostream>
#include <fstream>
#include <sstream>
#include <cstdio>
#include <cstdlib>
#include <vector>
#include <string>

#include "TestCase.h"
#include "HWTemplate.h"
//#include "../modules/modules.h"

int validateReadme();
int validateCompilation();
int validateTestCases();

int main( int argc, char* argv[] ) {
	
	// Check for valid directories
	
	// Run test cases
	validateReadme();
	
	validateTestCases();
	
	return 0;
}

/* Check student files directory for README.txt */
int validateReadme() {
	
	const char* readme = (student_files_dir + "/README.txt").c_str();
	std::ifstream instr( readme, std::ifstream::in );
	
	if( instr != NULL ) {
		// Handle output
		std::cout << "Readme found!" << std::endl;
	}
	else {
		std::cout << "Readme not found" << std::endl;
		return 1;	// README.txt does not exist
	}
	
	return 0;
}

/* Runs through each test case, pulls in the correct files, validates,
   and outputs the results */ 
int validateTestCases()
{
	for( int i = 0; i < num_testcases; ++i ) {
		std::cout << testcases[i].title() << " - points: " << testcases[i].points() << std::endl;
	}
	
	return 0;
}


