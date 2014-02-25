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
#include <sys/types.h>
#include <sys/stat.h>

#include "TestCase.h"
#include "HWTemplate.h"
//#include "../modules/modules.h"

bool checkValidDirectory( const std::string &directory );
int validateReadme();
int validateCompilation();
int validateTestCases();

int main( int argc, char* argv[] ) {
	
	// Check for valid directories
	if( !checkValidDirectory( input_files_dir ) ||
	    !checkValidDirectory( student_submit_dir ) ||
	    !checkValidDirectory( student_output_dir ) ||
	    !checkValidDirectory( expected_output_dir ) ||
	    !checkValidDirectory( results_dir ) ) {
	    
	    std::cout << "ERROR: one or more directories not found.  Terminating" << std::endl;
	    return 1;
	}
	
	// Run test cases
	validateReadme();
	
	validateTestCases();
	
	return 0;
}

/* Ensures that the given directory exists */
bool checkValidDirectory( const std::string &directory ) {
	
	struct stat status;
	stat( directory.c_str(), &status );
	if( !(status.st_mode & S_IFDIR) ) {
		std::cout << "ERROR: directory " << directory << " does not exist" << std::endl;
		return false;
	}
	else { std::cout << "Directory " << directory << " found!" << std::endl; }
	return true;
}

/* Check student submit directory for README.txt */
int validateReadme() {
	
	const char* readme = (student_submit_dir + "/README.txt").c_str();
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


