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
#include <iterator>
#include <sys/types.h>
#include <sys/stat.h>

#include "../modules/modules.h"
//#include "../modules/difference.h"
#include "TestCase.h"
#include "HWTemplate.h"

bool checkValidDirectory( const std::string &directory );
int validateReadme( std::ofstream &gradefile );
int validateCompilation( std::ofstream &gradefile );
int validateTestCases();

int main( int argc, char* argv[] ) {
	
	// Check for valid directories
	if( !checkValidDirectory( input_files_dir ) ||
	    !checkValidDirectory( student_submit_dir ) ||
	    !checkValidDirectory( student_output_dir ) ||
	    !checkValidDirectory( expected_output_dir ) ||
	    !checkValidDirectory( results_dir ) ) {
	    
	    std::cout << "ERROR: one or more directories not found" << std::endl;
	    return 1;
	}
	
	const char* gradepath = (student_submit_dir + "/submit_grade/grade.txt").c_str();
	std::ofstream outstr( gradepath, std::ofstream::out );
	
	// Run test cases
	validateReadme( outstr );
	
	validateTestCases();
	
	return 0;
}

/* Ensures that the given directory exists */
bool checkValidDirectory( const std::string &directory ) {
	
	struct stat status;
	stat( directory.c_str(), &status );
	if( !(status.st_mode & S_IFDIR) ) {
		std::cout << "ERROR: directory " << directory << " does not exist"
				  << std::endl;
		return false;
	}
	else { std::cout << "Directory " << directory << " found!" << std::endl; }
	return true;
}

/* Check student submit directory for README.txt */
int validateReadme( std::ofstream &gradefile ) {
	
	const char* readme = (student_submit_dir + "/README.txt").c_str();
	std::ifstream instr( readme, std::ifstream::in );
	
	if( instr != NULL ) {
		// Handle output
		std::cout << "Readme found!" << std::endl;
		gradefile << "2" << std::endl;
	}
	else {
		std::cout << "Readme not found" << std::endl;
		gradefile << "0" << std::endl;
		return 1;	// README.txt does not exist
	}
	
	return 0;
}

/* Makes sure the code was compiled successfully */
int validateCompilation( std::ofstream &gradefile ) {

	// at compile time, file will be generated with g++ exit status;
	//  check this file for successful compilation

	return 0;
}

/* Runs through each test case, pulls in the correct files, validates,
   and outputs the results */
int validateTestCases() {

	for( int i = 2; i < num_testcases; ++i ) {
		
		std::cout << testcases[i].title() << " - points: "
				  << testcases[i].points() << std::endl;
		
		// Pull in student output & expected output
		const char* student_path = (student_output_dir + "/"
									+ testcases[i].filename()).c_str();
		std::ifstream student_instr( student_path, std::ifstream::in );
		if( !student_instr ) { std::cout << "ERROR: Student's "
							   << testcases[i].filename() << " does not exist"
							   << std::endl; }
		
		const char* expected_path = (expected_output_dir + "/"
									 + testcases[i].expected()).c_str();
		std::ifstream expected_instr( expected_path, std::ifstream::in );
		if( !expected_instr ) { std::cout << "ERROR: Expected output file "
								<< testcases[i].expected() << " does not exist"
								<< std::endl; }
		
		//if( !student_instr || !expected_instr ) continue;
		
		char cout_temp[student_output_dir.size() + 15];
		sprintf(cout_temp, "%s/test%d_cout.txt", student_output_dir.c_str(), i-1 );
		const char* cout_path = cout_temp;
		
		// Check cout and cerr
		/*const char* cout_path = (student_output_dir + "/test" + (char*)(i-1) +
													  "_cout.txt" ).c_str();*/
		std::ifstream cout_instr( cout_path, std::ifstream::in );
		if( testcases[i].coutCheck() != DONT_CHECK ) {
			if( !cout_instr ) { std::cout << "ERROR: test" << (i-1)
								<< "_cout.txt does not exist" << std::endl; }
			else {
				if( testcases[i].coutCheck() == WARN_IF_NOT_EMPTY ) {
					std::string content;
					cout_instr >> content;
					if( content.size() > 0 ) { std::cout << "WARNING: test"
							   << (i-1) << "_cout.txt is not empty" << std::endl; }
				}
				else if( testcases[i].coutCheck() == CHECK ) {
					std::cout << "Check test" << (i-1)
							<< "_cout.txt instead of output file" << std::endl;
				}
			}
		}
		
		char cerr_temp[student_output_dir.size() + 15];
		sprintf(cerr_temp, "%s/test%d_cout.txt", student_output_dir.c_str(), i-1 );
		const char* cerr_path = cerr_temp;
		
		/*const char* cerr_path = (student_output_dir + "/test" + (char*)(i-1) +
													  "_cerr.txt" ).c_str();*/
		std::ifstream cerr_instr( cerr_path, std::ifstream::in );
		if( testcases[i].cerrCheck() != DONT_CHECK ) {
			if( !cerr_instr ) { std::cout << "ERROR: test" << (i-1)
								<< "_cerr.txt does not exist" << std::endl; }
			else {
				if( testcases[i].cerrCheck() == WARN_IF_NOT_EMPTY ) {
					std::string content;
					cerr_instr >> content;
					if( content.size() > 0 ) { std::cout << "WARNING: test"
							   << (i-1) << "_cerr.txt is not empty" << std::endl; }
				}
				else if( testcases[i].cerrCheck() == CHECK ) {
					std::cout << "Check test" << (i-1) << "_cerr.txt" << std::endl;
				}
			}
		}
		
		//std::cout << cout_path << std::endl;
		//std::cerr << cerr_path << std::endl;
		
		/* TODO: change return type?? */
		int result = NULL;
		const std::string blank = "";
		
		if( !student_instr && !expected_instr )
			result = testcases[i].compare( blank, blank );
		else if( !student_instr && expected_instr != NULL ) {
			const std::string e = std::string( std::istreambuf_iterator<char>(expected_instr),
										   		std::istreambuf_iterator<char>() );
			result = testcases[i].compare( blank, e );
		}
		else if( student_instr != NULL && !expected_instr ) {
			const std::string s = std::string( std::istreambuf_iterator<char>(student_instr),
										   		std::istreambuf_iterator<char>() );
			result = testcases[i].compare( s, blank );
		}
		else {
			const std::string s = std::string( std::istreambuf_iterator<char>(student_instr),
										   		std::istreambuf_iterator<char>() );
			const std::string e = std::string( std::istreambuf_iterator<char>(expected_instr),
										   		std::istreambuf_iterator<char>() );
			result = testcases[i].compare( s, e );
		}
		
		/*
		// Pass files off to comparison function
		const std::string blank = "";
		const std::string student_file_string;
		if( !student_instr ) student_file_string = blank;
		else student_file_string = std::string( std::istreambuf_iterator<char>(student_instr),
										   std::istreambuf_iterator<char>() );
		
		const std::string expected_file_string;
		if( !expected_instr ) expected_file_string = blank;
		else expected_file_string = std::string( std::istreambuf_iterator<char>(student_instr),
										   std::istreambuf_iterator<char>() );
		
		// NOTE: This return type will be changed
		int result = testcases[i].compare( student_file_string, expected_file_string );
		*/
		
		/* TODO: Output to result file */
		//std::cout << result << std::endl;
		
	}
	return 0;
}

