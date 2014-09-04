/* FILENAME: Validator.cpp
 * YEAR: 2014
 * AUTHORS:
 *   Members of Rensselaer Center for Open Source (rcos.rpi.edu):
 *   Chris Berger
 *   Jesse Freitas
 *   Severin Ibarluzea
 *   Kiana McNellis
 *   Kienan Knight-Boehm
 *   Sam Seng
 * LICENSE: Please refer to 'LICENSE.md' for the conditions of using this code
 *
 * RELEVANT DOCUMENTATION:
 *
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

#include "grading/TestCase.cpp"  /* Should not #include a .cpp file */

bool checkValidDirectory(char *directory);
bool checkValidDirectory(const char *directory);
int validateTestCases(int subnum, const char *subtime/*, int readme,
						       int compiled*/);

int main(int argc, char *argv[]) {

  /* Check argument usage */
  if (argc != 4) {
#ifdef DEBUG
    std::cerr << "VALIDATOR USAGE: validator <submission#> "
      "<time-of-submission> <runner-result>" << std::endl;
#endif
    return 1;
  }
  

  // TODO: Apply a diff to readme?


  // Run test cases
  int rc =
    validateTestCases(atoi(argv[1]), argv[2]);
  if (rc > 0) {
#ifdef DEBUG
    std::cerr << "Validator terminated" << std::endl;
#endif
    return 1;
  }

  return 0;
}



/* Ensures that the given directory exists */
bool checkValidDirectory(char *directory) {

  if (access(directory, 0) == 0) {
    struct stat status;
    stat(directory, &status);
    if (status.st_mode & S_IFDIR) {
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
bool checkValidDirectory(const char *directory) {

  if (access(directory, 0) == 0) {
    struct stat status;
    stat(directory, &status);
    if (status.st_mode & S_IFDIR) {
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
int validateTestCases(int subnum, const char *subtime /*, int readme,
							 int compiled*/) {

  std::string grade_path = ".submit.grade";
  std::ofstream gradefile(grade_path.c_str());

  gradefile << "Grade for: XXX" << std::endl;
  gradefile << "  submission#: " << subnum << std::endl;


  int total_grade = 0;
  int nonhidden_total_grade = 0;
  std::stringstream testcase_json;


  // LOOP OVER ALL TEST CASES
  for (int i = 0; i < num_testcases; ++i) {
    std::cout << "------------------------------------------\n" << testcases[i].title() << " - points: " << testcases[i].points() << std::endl;
    
    // START JSON FOR TEST CASE
    testcase_json << "\t\t{\n"
                  << "\t\t\t\"test_name\": \"" << testcases[i].title() << "\",\n";
    int testcase_grade = 0;
    std::string message = "";

    if (testcases[i].isFileExistsTest()  ||  testcases[i].isCompilationTest()) {
      // FILE EXISTS & COMPILATION TESTS DON'T HAVE FILE COMPARISONS
      std::cerr << "THIS IS A FILE EXISTS TEST! " << std::endl;
      if ( access( testcases[i].details().c_str(), F_OK|R_OK|W_OK ) != -1 ) { /* file exists */
	std::cerr << "file does exist: " << testcases[i].details() << std::endl;
	testcase_grade = testcases[i].points();
      } else {
	std::cerr << "ERROR file DOES NOT exist: " << testcases[i].details() << std::endl;
	message += "ERROR: " + testcases[i].details() + " was NOT FOUND!";
      }

      if (testcases[i].isCompilationTest()) {
	testcase_json << "\t\t\t\"compilation_output\": \".submit_compilation_output.txt\",\n";
      }
      
    }
    else {
      // ALL OTHER TESTS HAVE 1 OR MORE FILE COMPARISONS
      testcase_json << "\t\t\t\"diffs\": [\n";
      float grade_helper = 1.0;
      //      std::cerr << "-----------------------\ntest case " << i+1 << std::endl;
      for (int j = 0; j < testcases[i].numFileComparisons(); j++) {
	std::cerr << "comparison #" << j << std::endl;
	std::string helper_message = "";

	bool ok_to_compare = true;

	// GET THE FILES READY
	std::ifstream student_instr(testcases[i].filename(j).c_str());
	if (!student_instr) {
	  std::stringstream tmp;
	  tmp << "ERROR: comparison #" << j << ": Student's " << testcases[i].filename(j) << " does not exist";
	  std::cerr << tmp.str() << std::endl;
	  helper_message += tmp.str();
	  ok_to_compare = false;
	} 
	std::ifstream expected_instr(testcases[i].expected(j).c_str());
	if (!expected_instr && testcases[i].expected(j) != "") {
	  std::stringstream tmp;
	  tmp << "ERROR: comparison #" << j << ": Instructor's " + testcases[i].expected(j) + " does not exist!";
	  std::cerr << tmp.str() << std::endl;
	  if (helper_message != "") helper_message += "<br>";
	  helper_message += tmp.str();
	  ok_to_compare = false;
	}

	// DO THE COMPARISON
	TestResults *result = NULL;
	if (ok_to_compare) {
	  result = testcases[i].compare(j);
	} 

	// PREPARE THE JSON DIFF FILE
	std::stringstream diff_path;
	diff_path << testcases[i].prefix() << "_" << j << "_diff.json";
	std::ofstream diff_stream(diff_path.str().c_str());

	if (result != NULL) {
	  // THE GRADE (will be compiled across all comparisons)
	  std::cout << "result->grade() " << result->grade() << std::endl;
	  grade_helper *= result->grade();
	  result->printJSON(diff_stream);
	  
	  helper_message += " " + result->get_message();
	
	  // CLEANUP THIS COMPARISON
	  delete result;
	} else {
	  grade_helper = 0;
	}

	// JSON FOR THIS COMPARISON
	std::stringstream expected_path;
	expected_path << expected_out_dir << testcases[i].expected(j);
	testcase_json
	  << "\t\t\t\t{\n"
	  << "\t\t\t\t\t\"diff_id\":\"" << testcases[i].prefix() << "_" << j << "_diff\",\n"
	  << "\t\t\t\t\t\"student_file\":\"" << testcases[i].filename(j) << "\",\n";
	if (testcases[i].expected(j) != "") {
	  testcase_json << "\t\t\t\t\t\"instructor_file\":\"" << expected_path.str() << "\",\n";
	  if (ok_to_compare) {
	    testcase_json << "\t\t\t\t\t\"difference\":\"" << testcases[i].prefix() << "_" << j << "_diff.json\",\n";
	  }
	}
	testcase_json << "\t\t\t\t\t\"description\": \"" << testcases[i].description(j) << "\",\n";
	if (helper_message != "") {
	  testcase_json << "\t\t\t\t\t\"message\": \"" << helper_message << "\",\n";
	}
	testcase_json << "\t\t\t\t},\n";
      } // END COMPARISON LOOP

      testcase_json << "\t\t\t],\n";
      testcase_grade = (int)floor(grade_helper * testcases[i].points());

    } // end if/else of test case type

    // output grade & message

    std::cout << "Grade: " << testcase_grade << std::endl;
    total_grade += testcase_grade;
    if (!testcases[i].hidden()) nonhidden_total_grade += testcase_grade;
    testcase_json << "\t\t\t\"points_awarded\": " << testcase_grade << ",\n";

    if (message != "") {
      testcase_json << "\t\t\t\"message\": \"" << message << "\",\n";
    }

    const char *last_line = (i == num_testcases - 1) ? "\t\t}\n" : "\t\t},\n";
    testcase_json << last_line;


    gradefile << "  Test " << std::setw(2) << std::right << i+1 << ":" 
	      << std::setw(30) << std::left << testcases[i].just_title() << " " 
	      << std::setw(2) << std::right << testcase_grade << " / " 
	      << std::setw(2) << std::right << testcases[i].points() << std::endl;

  } // end test case loop



  /* Generate submission.json */
  std::ofstream json_file("submission.json");
  json_file << "{\n"
            << "\t\"submission_number\": " << subnum << ",\n"
            << "\t\"points_awarded\": " << total_grade << ",\n"
            << "\t\"nonhidden_points_awarded\": " << nonhidden_total_grade << ",\n"
            << "\t\"submission_time\": \"" << subtime << "\",\n"
            << "\t\"testcases\": [\n";
  json_file << testcase_json.str() << "\t]\n"
	    << "}";
  json_file.close();



  gradefile << "Automatic extra credit (w/o hidden):"                << "+ " << 0 << " points" << std::endl;
  gradefile << "Automatic grading total (w/o hidden):"               << total_grade << " / " << 0 << std::endl;
  gradefile << "Max possible hidden automatic grading points:"       << 0 << std::endl;
  gradefile << "Automatic extra credit:"                             << "+ " << 0 << " points" << std::endl;
  gradefile << "Automatic grading total:"                            << nonhidden_total_grade << " / " << 0 << std::endl;
  gradefile << "Remaining points to be graded by TA:"                << 0 << std::endl;
  gradefile << "Max points for assignment (excluding extra credit):" << 0 << std::endl;






  return 0;
}
