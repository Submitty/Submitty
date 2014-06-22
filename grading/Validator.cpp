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
#include <typeinfo>
#include <sys/types.h>
#include <sys/stat.h>
#include <math.h>
#include <unistd.h>

#include "modules/modules.h"
#include "grading/TestCase.h"

/* TODO: how to include this specifically? */
/*  maybe -include with g++ */
//#include "../sampleConfig/HW1/config.h"

bool checkValidDirectory( char* directory );
bool checkValidDirectory( const char* directory );
int validateTestCases( int subnum, const char* subtime, int readme, int compiled );

int main( int argc, char* argv[] ) {

	/* Check argument usage */
	if( argc != 4 ) {
#ifdef DEBUG
		std::cerr << "VALIDATOR USAGE: validator <submission#> <time-of-submission> <runner-result>" << std::endl;
#endif
		return 1;
	}

	// Check for readme
	bool readme_found = false;

	if(access("README.txt", 0) == 0) {
		struct stat status;
		stat( "README.txt", &status );
		if( status.st_mode & S_IFREG ) {
#ifdef DEBUG
			std::cout << "Readme found!" << std::endl;
#endif
			readme_found = true;
		}
	}
	if(!readme_found) {
#ifdef DEBUG
		std::cout << "Readme not found!" << std::endl;
#endif
	}

	// TODO: Apply a diff to readme?

	// Run test cases
	int rc = validateTestCases( atoi(argv[1]), argv[2], readme_found, atoi(argv[3]) );
	if(rc > 0) {
#ifdef DEBUG
		std::cerr << "Validator terminated" << std::endl;
#endif
		return 1;
	}

	return 0;
}

/* Ensures that the given directory exists */
bool checkValidDirectory( char* directory ) {

	if(access(directory, 0) == 0) {
		struct stat status;
		stat( directory, &status );
		if( status.st_mode & S_IFDIR ) {
#ifdef DEBUG
			std::cout << "Directory " << directory << " found!" << std::endl;
#endif
			return true;
		}
	}
#ifdef DEBUG
	std::cerr << "ERROR: directory " << directory << " does not exist"
			  << std::endl;
#endif
	return false;
}

// checkValidDirectory with const char*
bool checkValidDirectory( const char* directory ) {

	if(access(directory, 0) == 0) {
		struct stat status;
		stat( directory, &status );
		if( status.st_mode & S_IFDIR ) {
#ifdef DEBUG
			std::cout << "Directory " << directory << " found!" << std::endl;
#endif
			return true;
		}
	}
#ifdef DEBUG
	std::cerr << "ERROR: directory " << directory << " does not exist"
			  << std::endl;
#endif
	return false;
}

/* Runs through each test case, pulls in the correct files, validates,
   and outputs the results */
int validateTestCases( int subnum, const char* subtime, int readme, int compiled ) {

	int total_grade = 0;

	std::stringstream testcase_json;

	int index = 2;
	if(compileTestCase == NULL) index--;
	int t = 1;
	for( int i = index; i < num_testcases; ++i ) {

		std::cout << testcases[i].title() << " - points: "
				  << testcases[i].points() << std::endl;

		// Pull in student output & expected output
		std::ifstream student_instr( testcases[i].filename().c_str() );
		if( !student_instr ) {
#ifdef DEBUG
			std::cerr << "ERROR: Student's " << testcases[i].filename()
					  << " does not exist" << std::endl;
#endif
			continue;
		}

		std::ifstream expected_instr( testcases[i].expected().c_str() );
		if( !expected_instr ) {
#ifdef DEBUG
			std::cerr << "ERROR: Instructor's " << testcases[i].expected()
					  << " does not exist" << std::endl;
#endif
			continue;
		}

		// Check cout and cerr
		std::stringstream cout_path;
		cout_path << "test" << t << "_cout.txt";
		std::ifstream cout_instr( cout_path.str().c_str() );
		if( testcases[i].coutCheck() != DONT_CHECK ) {
			if( !cout_instr ) { std::cerr << "ERROR: test" << t
								<< "_cout.txt does not exist" << std::endl; }
			else {
				if( testcases[i].coutCheck() == WARN_IF_NOT_EMPTY ) {
					std::string content;
					cout_instr >> content;
					if( content.size() > 0 ) { std::cout << "WARNING: test"
							   << t << "_cout.txt is not empty" << std::endl; }
				}
				else if( testcases[i].coutCheck() == CHECK ) {
					std::cout << "Check test" << t
							<< "_cout.txt instead of output file" << std::endl;
				}
			}
		}

		std::stringstream cerr_path;
		cerr_path << "test" << t << "_cerr.txt";
		std::ifstream cerr_instr( cerr_path.str().c_str() );
		if( testcases[i].cerrCheck() != DONT_CHECK ) {
			if( !cerr_instr ) { std::cout << "ERROR: test" << t
								<< "_cerr.txt does not exist" << std::endl; }
			else {
				if( testcases[i].cerrCheck() == WARN_IF_NOT_EMPTY ) {
					std::string content;
					cerr_instr >> content;
					if( content.size() > 0 ) { std::cout << "WARNING: test"
							   << t << "_cerr.txt is not empty" << std::endl; }
				}
				else if( testcases[i].cerrCheck() == CHECK ) {
					std::cout << "Check test" << t << "_cerr.txt" << std::endl;
				}
			}
		}

		TestResults* result;
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

		/* TODO: Always returns 0 ? */

		int testcase_grade = 0;

		std::stringstream diff_path;
		diff_path << "test" << t << "_diff.json";
		std::ofstream diff_stream(diff_path.str().c_str());

		std::cout << result->grade() << std::endl;

		testcase_grade = (int)floor(result->grade() * testcases[i].points());
		result->printJSON(diff_stream);

		std::cout << "Grade: " << testcase_grade << std::endl;

		const char* last_line = (i == num_testcases-1) ? "\t\t}\n" : "\t\t},\n";

		std::stringstream expected_path;
		expected_path << expected_out_dir << testcases[i].expected();

		// Generate JSON data
		testcase_json << "\t\t{\n"
					  << "\t\t\t\"test_name\": \"" << testcases[i].title() << "\",\n"
					  << "\t\t\t\"points_awarded\": " << testcase_grade << ",\n"
					  << "\t\t\t\"diff\":{\n"
					  << "\t\t\t\t\"instructor_file\":\"" << expected_path.str() << "\",\n"
					  << "\t\t\t\t\"student_file\":\"" << testcases[i].filename() << "\",\n"
					  << "\t\t\t\t\"difference\":\"test" << t << "_diff.json\"\n"
					  << "\t\t\t}\n"
					  << last_line;
		++t;

		delete result;
	}

	/* Get readme and compilation grades */
	int readme_grade = (readme == 1) ? readme_pts : 0;
	const char* readme_msg = (readme == 1)? "README found" : "README not found";

	int compile_grade = 0;
	const char* compile_msg = "";
	if(compileTestCase != NULL) {
		compile_grade = (compiled == 0) ? compile_pts : 0;
		compile_msg = (compiled == 0)? "Compiled successfully" : "Compilation failed";
	}

	bool handle_compilation = (compileTestCase != NULL);

	total_grade += (readme_grade + compile_grade);

	/* Output total grade */
	std::string grade_path = "grade.txt";
	std::ofstream gradefile(grade_path.c_str());
	gradefile << total_grade << std::endl;

	/* Generate submission.json */
	std::ofstream json_file("submission.json");
	json_file << "{\n"
			  << "\t\"submission_number\": " << subnum << ",\n"
			  << "\t\"points_awarded\": " << total_grade << ",\n"
			  << "\t\"submission_time\": \"" << subtime << "\",\n"
			  << "\t\"testcases\": [\n"
			  << "\t\t{\n"
			  << "\t\t\t\"test_name\": \"Readme\",\n"
			  << "\t\t\t\"points_awarded\": " << readme_grade << ",\n"
			  << "\t\t\t\"message\": \"" << readme_msg << "\",\n"
			  << "\t\t},\n";
	if(handle_compilation) {
		json_file << "\t\t{\n"
				  << "\t\t\t\"test_name\": \"Compilation\",\n"
				  << "\t\t\t\"points_awarded\": " << compile_grade << ",\n"
				  << "\t\t\t\"message\": \"" << compile_msg << "\",\n"
				  << "\t\t},\n";
	}
	json_file << testcase_json.str()
			  << "\t]\n"
			  << "}";
	json_file.close();

	return 0;
}
