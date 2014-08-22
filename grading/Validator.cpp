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

  // Check for readme
  //bool readme_found = false;


  /*
  if (access("README.txt", 0) == 0) {
    struct stat status;
    stat("README.txt", &status);
    if (status.st_mode & S_IFREG) {
#ifdef DEBUG
      std::cout << "Readme found!" << std::endl;
#endif
      readme_found = true;
    }
  }
  if (!readme_found) {
#ifdef DEBUG
    std::cout << "README not found!" << std::endl;
#endif
  }
  */

  // TODO: Apply a diff to readme?

  // Run test cases
  int rc =
    validateTestCases(atoi(argv[1]), argv[2] /*, readme_found, atoi(argv[3])*/);
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

  int total_grade = 0;

  std::stringstream testcase_json;


  //  int t = 1;
  //for (int i = index; i < num_testcases; ++i) {
  for (int i = 0; i < num_testcases; ++i) {
    
    std::cout << testcases[i].title() << " - points: " << testcases[i].points()
              << std::endl;

    
    /* TODO: Always returns 0 ? */
    int testcase_grade = 0;
    
    bool has_diff = false;
    
    std::string message = "";
    
    if (testcases[i].isFileExistsTest() ||
	testcases[i].isCompilationTest()) {
      std::cerr << "THIS IS A FILE EXISTS TEST! " << std::endl;
      
      if ( access( testcases[i].details().c_str(), F_OK|R_OK|W_OK ) != -1 ) { /* file exists */
	std::cerr << "file does exist: " << testcases[i].details() << std::endl;
	testcase_grade = testcases[i].points();
	message = testcases[i].details() + " was found";
      } else {
	std::cerr << "ERROR file DOES NOT exist: " << testcases[i].details() << std::endl;
	message = "ERROR: " + testcases[i].details() + " was NOT FOUND!";
      }
      
    }
    
    else {

      std::cout << "num comparisons " << testcases[i].numFileComparisons() << std::endl;
      assert (testcases[i].numFileComparisons() > 0);
      // Pull in student output & expected output
      std::ifstream student_instr(testcases[i].filename(0).c_str());
      if (!student_instr) {
	//#ifdef DEBUG
	std::cerr << "ERROR: Student's " << testcases[i].filename(0)
		  << " does not exist" << std::endl;
	//#endif
	//continue;
	//
	//assert (testcases[i].numFileComparisons() > 0);
	//if  (testcases[i].numFileComparisons() > 0) {
	  message = "ERROR: Student's expected output " + testcases[i].raw_filename(0) + " was not created!";
	  //}
      } else  {
	


      std::cout << "num comparisons " << testcases[i].numFileComparisons() << std::endl;
      assert (testcases[i].numFileComparisons() > 0);

	std::ifstream expected_instr(testcases[i].expected(0).c_str());
	if (!expected_instr) {
	  //#ifdef DEBUG
	  std::cerr << "HOMEWORK CONFIGURATION ERROR: Instructor's " << testcases[i].expected(0)
		    << " does not exist" << std::endl;
	  message = "HOMEWORK CONFIGURATION ERROR: Instructor's " + testcases[i].expected(0)
								  + " does not exist!";
	  //#endif
	  //continue;
	} else {
	  
	  // Check cout and cerr
	  /*

	  std::stringstream cout_path;
	  cout_path << testcases[i].prefix() << "_cout.txt";
	  std::ifstream cout_instr(cout_path.str().c_str());
	  if (testcases[i].coutCheck() != DONT_CHECK) {
	    if (!cout_instr) {
	      std::cerr << "ERROR: " << testcases[i].prefix() << "_cout.txt does not exist"
			<< std::endl;
	    } else {

	      if (testcases[i].coutCheck() == WARN_IF_NOT_EMPTY) {
		std::string content;
		cout_instr >> content;
		if (content.size() > 0) {
		  std::cout << "WARNING: " << testcases[i].prefix() << "_cout.txt is not empty"
			    << std::endl;
		}
	      } else if (testcases[i].coutCheck() == CHECK) {
		std::cout << "Check " << testcases[i].prefix() << "_cout.txt instead of output file"
			  << std::endl;
	      }
	    }
	  }
	  
	  std::stringstream cerr_path;
	  cerr_path << testcases[i].prefix() << "_cerr.txt";
	  std::ifstream cerr_instr(cerr_path.str().c_str());
	  if (testcases[i].cerrCheck() != DONT_CHECK) {
	    if (!cerr_instr) {
	      std::cout << "ERROR: " << testcases[i].prefix() << "_cerr.txt does not exist"
			<< std::endl;
	    } else {
	      if (testcases[i].cerrCheck() == WARN_IF_NOT_EMPTY) {
		std::string content;
		cerr_instr >> content;
		if (content.size() > 0) {
		  std::cout << "WARNING: " << testcases[i].prefix() << "_cerr.txt is not empty"
			    << std::endl;
		}
	      } else if (testcases[i].cerrCheck() == CHECK) {
		std::cout << "Check " << testcases[i].prefix() << "_cerr.txt" << std::endl;
	      }
	    }
	  }
	  */
      
	  TestResults *result;
	  
	  //const std::string blank = "";
	  
	  /*
	  if (!student_instr && !expected_instr)
	    result = testcases[i].compare(blank, blank);
	  else if (!student_instr && expected_instr != NULL) {
	    const std::string e =
	      std::string(std::istreambuf_iterator<char>(expected_instr),
			  std::istreambuf_iterator<char>());
	    result = testcases[i].compare(blank, e);
	  } else if (student_instr != NULL && !expected_instr) {
	    const std::string s =
	      std::string(std::istreambuf_iterator<char>(student_instr),
			  std::istreambuf_iterator<char>());
	    result = testcases[i].compare(s, blank);
	  } else {
	    const std::string s =
	      std::string(std::istreambuf_iterator<char>(student_instr),
			  std::istreambuf_iterator<char>());
	    const std::string e =
	      std::string(std::istreambuf_iterator<char>(expected_instr),
			  std::istreambuf_iterator<char>());
	    result = testcases[i].compare(s, e);
	  }
	  */


	  result = testcases[i].compare(0); //s, e);

	  has_diff = true;
	  
	  std::cout << "MAKING A DIFF JSON" << std::endl;
	  std::stringstream diff_path;
	  diff_path << testcases[i].prefix() << "_diff.json";
	  std::ofstream diff_stream(diff_path.str().c_str());
	  
	  std::cout << "result->grade() " << result->grade() << std::endl;
	  
	  // had to edit to invert the grade??
	  testcase_grade = (int)floor(result->grade() * testcases[i].points());
	  result->printJSON(diff_stream);
	  
	  std::cout << "Grade: " << testcase_grade << std::endl;
	  
	  delete result;
	}
      }
    }      
    
    const char *last_line = (i == num_testcases - 1) ? "\t\t}\n" : "\t\t},\n";



    total_grade += testcase_grade;

    // Generate JSON data
    testcase_json << "\t\t{\n"
                  << "\t\t\t\"test_name\": \"" << testcases[i].title()
                  << "\",\n"
                  << "\t\t\t\"points_awarded\": " << testcase_grade << ",\n";

    if (has_diff) {

      assert (testcases[i].numFileComparisons() > 0);
      std::stringstream expected_path;
      expected_path << expected_out_dir << testcases[i].expected(0);
      
      testcase_json << "\t\t\t\"diff\":{\n"

		    << "\t\t\t\t\"diff_id\":\"diff_test" << i << "_" << 0
		    << "\",\n"
		    << "\t\t\t\t\"instructor_file\":\"" << expected_path.str()
		    << "\",\n"
		    << "\t\t\t\t\"student_file\":\"" << testcases[i].filename(0)
		    << "\",\n"
		    << "\t\t\t\t\"difference\":\"" << testcases[i].prefix() << "_diff.json\"\n"
		    << "\t\t\t},\n";
    }
    if (message != "") {
      testcase_json << "\t\t\t\"message\": \"" << message << "\",\n";
    }
    if (testcases[i].isCompilationTest()) {
      testcase_json << "\t\t\t\"compilation_output\": \".submit_compilation_output.txt\",\n";
    }
    
    testcase_json << last_line;
    //++t;


  }

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
            << "\t\"testcases\": [\n";


  json_file << testcase_json.str() << "\t]\n"
            << "}";
  json_file.close();

  return 0;
}
