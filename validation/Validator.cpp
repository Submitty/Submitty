/* Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
Kiana McNellis, Kienan Knight-Boehm, Sam Seng

All rights reserved.
This code is licensed using the BSD "3-Clause" license. Please refer to
"LICENSE.md" for the full license.
*/

#include <iostream>
#include <fstream>
#include <sstream>
#include <cstdio>
#include <cstdlib>
#include <cstring>
#include <vector>
#include <string>
#include <iterator>
#include <sys/types.h>
#include <sys/stat.h>

#include "../modules/modules.h"
#include "TestCase.h"

/* TODO: how to include this specifically? */
/*  maybe -D this on the command line */
#include "config.h"

bool checkValidDirectory( char* directory );
bool checkValidDirectory( const char* directory );
int validateReadme( char* submit_dir, char* grade_dir );
int validateCompilation( std::ofstream &gradefile );
int validateTestCases( char* submit_dir, char* grade_dir );

int main( int argc, char* argv[] ) {
	
	/* Check argument usage */
	if( argc != 4 ) {
#ifdef DEBUG		
		std::cerr << "VALIDATOR USAGE: validator <homework#> <username> <submission#>" << std::endl;
#endif		
		return 1;
	}
	
	/* TODO: Will this need to be a CL arg? */
	//const char* root_dir = "CSCI1200";
	
	/*
	char hw_dir[strlen(root_dir)+strlen(argv[1])+1];
	sprintf(hw_dir, "%s/HW%s", root_dir, argv[1]);
	hw_dir[strlen(hw_dir)] = '\0';
	*/
	
	char user_dir[strlen(root_dir)+strlen(hw_dir)+strlen(argv[2])+2];
	sprintf(user_dir, "%s%s%s/", root_dir, hw_dir, argv[2]);
	user_dir[strlen(user_dir)] = '\0';
	
	char submission_dir[strlen(user_dir)+strlen(argv[3])+2];
	sprintf(submission_dir, "%s%s/", user_dir, argv[3]);
	submission_dir[strlen(submission_dir)] = '\0';
	
	/* Check for valid directories */
	if( !checkValidDirectory( hw_dir ) ||
	    !checkValidDirectory( user_dir ) ||
	    !checkValidDirectory( submission_dir )) {

#ifdef DEBUG	    
	    std::cerr << "ERROR: one or more directories not found" << std::endl;
	    return 1;
#endif
	}
	
	char grade_dir[strlen(submission_dir)+strlen(".submit.grade/")+1];
	sprintf(grade_dir, "%s.submit.grade/", submission_dir);
	grade_dir[strlen(grade_dir)] = '\0';
	
	//std::ofstream outstr( grade_dir, std::ofstream::out );
	
	// Run test cases
	validateReadme( submission_dir, grade_dir );
	
	validateTestCases( submission_dir, grade_dir );
	
	return 0;
}

/* Ensures that the given directory exists */
bool checkValidDirectory( char* directory ) {
	
	struct stat status;
	stat( directory, &status );
	if( !(status.st_mode & S_IFDIR) ) {
#ifdef DEBUG
		std::cerr << "ERROR: directory " << directory << " does not exist"
				  << std::endl;
#endif		
		return false;
	}
#ifdef DEBUG
	else {
		std::cout << "Directory " << directory << " found!" << std::endl;
	}
#endif
	return true;
}

// checkValidDirectory with const char*
bool checkValidDirectory( const char* directory ) {
	
	struct stat status;
	stat( directory, &status );
	if( !(status.st_mode & S_IFDIR) ) {
#ifdef DEBUG
		std::cerr << "ERROR: directory " << directory << " does not exist"
				  << std::endl;
#endif		
		return false;
	}
#ifdef DEBUG
	else {
		std::cout << "Directory " << directory << " found!" << std::endl;
	}
#endif
	return true;
}

/* Check student submit directory for README.txt */
int validateReadme( char* submit_dir, char* grade_dir ) {
	
	char gradepath[strlen(grade_dir)+strlen("grade.txt")+1];
	sprintf(gradepath, "%sgrade.txt", grade_dir);
	gradepath[strlen(gradepath)] = '\0';
	
	std::ofstream gradefile( gradepath, std::ofstream::out );
	
	char readme[strlen(submit_dir)+strlen("README.txt")+1];
	sprintf(readme, "%sREADME.txt", submit_dir);
	readme[strlen(readme)] = '\0';
	
	std::ifstream instr( readme, std::ifstream::in );
	
	if( instr != NULL ) {
		// Handle output
#ifdef DEBUG
		std::cout << "Readme found!" << std::endl;
#endif		
		/* TODO: Specify readme points */
		gradefile << "2" << std::endl;
	}
	else {
#ifdef DEBUG		
		std::cout << "Readme not found" << std::endl;
#endif		
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
int validateTestCases( char* submit_dir, char* grade_dir ) {

	char output_dir[strlen(submit_dir)+strlen(".submit.out/")+1];
	sprintf(output_dir, "%s.submit.out/", submit_dir);
	output_dir[strlen(output_dir)] = '\0';
	
	for( int i = 2; i < num_testcases; ++i ) {
		
		std::cout << testcases[i].title() << " - points: "
				  << testcases[i].points() << std::endl;
		
		// Pull in student output & expected output
		const char* student_path = (output_dir + testcases[i].filename()).c_str();
		std::ifstream student_instr( student_path, std::ifstream::in );
#ifdef DEBUG		
		if( !student_instr ) { std::cout << "ERROR: Student's "
							   << testcases[i].filename() << " does not exist"
							   << std::endl; }
#endif		
		const char* expected_path = (expected_out_dir + testcases[i].expected()).c_str();
		std::ifstream expected_instr( expected_path, std::ifstream::in );
#ifdef DEBUG		
		if( !expected_instr ) { std::cout << "ERROR: Expected output file "
								<< testcases[i].expected() << " does not exist"
								<< std::endl; }
#endif
		//if( !student_instr || !expected_instr ) continue;
		
		char cout_temp[strlen(output_dir) + 16];
		sprintf(cout_temp, "%stest%d_cout.txt", output_dir, i-1 );
		const char* cout_path = cout_temp;
		
		// Check cout and cerr
		/*const char* cout_path = (student_output_dir + "/test" + (char*)(i-1) +
													  "_cout.txt" ).c_str();*/
		std::ifstream cout_instr( cout_path, std::ifstream::in );
		if( testcases[i].coutCheck() != DONT_CHECK ) {			
			if( !cout_instr ) { std::cerr << "ERROR: test" << (i-1)
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
		
		char cerr_temp[strlen(output_dir) + 16];
		sprintf(cerr_temp, "%stest%d_cerr.txt", output_dir, i-1 );
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
		
		Difference result;
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
		
		/* TODO: Pass off to grade interpreter? */
		
		std::cout << "distance: " << (result.distance) << std::endl;
		
		
		
	}
	return 0;
}

