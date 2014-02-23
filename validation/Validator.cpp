/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
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

#include "HWTemplate.h"
#include "HWserver/validation/TestCase.h"
//#include [validation modules]

//int parse_settings_file( ifstream &instr ) {}
bool checkReadme( const std::string &filename );
void runTestCases();
void prepareGradeFile();

int main( int argc, char * argv[] )
{
	/*const char* settings_filepath = "C:/Users/bergec5/Desktop/server_example/Homework1/hw1_config.txt";
	
	ifstream instr(settings_filepath);
	if ( !instr )
	{
		cerr << "Error: Can't open file '" << settings_filepath << "'" << endl;
		exit(1);
	}
	
	parse_settings_file(instr);*/
	
	// Check for readme file
	if( checkReadme( "README.txt" ) )
	{
		// where to print??
		std::cout << "Readme found" << std::endl;
	}
	else
	{
		std::cout << "Readme not found" << std::endl;
	}
	
	return 0;
}

bool checkReadme( const std::string &filename )
{
	FILE* readme = fopen( "testHWsubmit/" + filename, "r" );
	
	return (readme != NULL);
}

void runTestCases()
{
	/* Doesn't actually "execute" the different cases, but just runs through each
	test case, pulls in the correct 
}

// Read in and parse settings file
/*int parse_settings_file( ifstream &instr )
{
	string prefix;
	string value;
	
	while( instr >> prefix >> value )
	{
		if      ( prefix == "HOMEWORK_NUMBER" ) { stringstream(value) >> hw_num; }
		else if ( prefix == "HOMEWORK_NAME" ) { hw_name = value; }
		else if ( prefix == "AUTOGRADE_POINTS" ) { stringstream(value) >> auto_pts; }
		else if ( prefix == "TA_POINTS" ) { stringstream(value) >> ta_pts; }
		else if ( prefix == "SUBMISSIONS_ALLOWED" ) { stringstream(value) >> max_submissions; }
		else if ( prefix == "PENALTY" ) { stringstream(value) >> submission_penalty; }
		else if ( prefix == "INPUT_FILE_DIRECTORY" ) { input_file_dir = value; }
		else if ( prefix == "OUTPUT_FILE_DIRECTORY" ) { output_file_dir = value; }
		else if ( prefix == "MAX_CLOCK_TIME" ) { stringstream(value) >> max_clocktime; }
		else if ( prefix == "MAX_CPU_TIME" ) { stringstream(value) >> max_cputime; }
		else if ( prefix == "MAX_OUTPUT_SIZE" ) { stringstream(value) >> max_output_size; }
		else if ( prefix == "README_POINTS" ) { stringstream(value) >> readme_pts; }
		else if ( prefix == "COMPILATION_POINTS" ) { stringstream(value) >> compile_pts; }
		else if ( prefix == "TEST_CASES" ) { stringstream(value) >> num_testcases; }
		else
		{
			// unrecognized setting; for now, ignore
			cout << "Unrecognized setting: " << prefix << endl;
		}
	}
	
	cout << max_submissions << endl;
}*/


