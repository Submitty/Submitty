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


  /* WHOOPS!  This seems to be a hack to handle the readme & compilation test cases and must go away */
  /*  int index = 2;*/
  /*  if (compileTestCase == NULL)
    index--;
  */

  //  int t = 1;
  //for (int i = index; i < num_testcases; ++i) {
  for (int i = 0; i < num_testcases; ++i) {

    std::cout << testcases[i].title() << " - points: " << testcases[i].points()
              << std::endl;


     /* TODO: Always returns 0 ? */
      int testcase_grade = 0;

      bool has_diff = false;

      std::string message = "";

    if (testcases[i].command() == std::string("FILE_EXISTS")) {
      std::cerr << "THIS IS A FILE EXISTS TEST! " << std::endl;

      if ( access( testcases[i].raw_filename().c_str(), F_OK|R_OK|W_OK ) != -1 ) { /* file exists */
	std::cerr << "file does exist: " << testcases[i].raw_filename() << std::endl;
	testcase_grade = testcases[i].points();
	message = testcases[i].raw_filename() + " was found";
      } else {
	std::cerr << "ERROR file DOES NOT exist: " << testcases[i].raw_filename() << std::endl;
	message = "ERROR: " + testcases[i].raw_filename() + " was NOT FOUND!";
      }

    }

    else {

      // Pull in student output & expected output
      std::ifstream student_instr(testcases[i].filename().c_str());
      if (!student_instr) {
	//#ifdef DEBUG
	std::cerr << "ERROR: Student's " << testcases[i].filename()
		  << " does not exist" << std::endl;
	//#endif
	//continue;
	message = "ERROR: Student's expected output " + testcases[i].raw_filename() + " was not created!";
      } else  {
	
	std::ifstream expected_instr(testcases[i].expected().c_str());
	if (!expected_instr) {
	  //#ifdef DEBUG
	  std::cerr << "HOMEWORK CONFIGURATION ERROR: Instructor's " << testcases[i].expected()
		    << " does not exist" << std::endl;
	  message = "HOMEWORK CONFIGURATION ERROR: Instructor's " + testcases[i].expected()
								  + " does not exist!";
	  //#endif
	  //continue;
	} else {
	  
	  // Check cout and cerr
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
      
	  TestResults *result;
	  
	  const std::string blank = "";
	  
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


    std::stringstream expected_path;
    expected_path << expected_out_dir << testcases[i].expected();

    total_grade += testcase_grade;

    // Generate JSON data
    testcase_json << "\t\t{\n"
                  << "\t\t\t\"test_name\": \"" << testcases[i].title()
                  << "\",\n"
                  << "\t\t\t\"points_awarded\": " << testcase_grade << ",\n";

    if (has_diff) {
      testcase_json << "\t\t\t\"diff\":{\n"
		    << "\t\t\t\t\"instructor_file\":\"" << expected_path.str()
		    << "\",\n"
		    << "\t\t\t\t\"student_file\":\"" << testcases[i].filename()
		    << "\",\n"
		    << "\t\t\t\t\"difference\":\"" << testcases[i].prefix() << "_diff.json\"\n"
		    << "\t\t\t}\n";
    }
    if (message != "") {
      testcase_json << "\t\t\t\"message\": \"" << message << "\",\n";
    }
    
    testcase_json << last_line;
    //++t;


  }

  /* Get readme and compilation grades */
  //int readme_grade = (readme == 1) ? readme_pts : 0;
  //const char *readme_msg = (readme == 1) ? "README found" : "README not found";

#if 0
  /*
 int compile_grade = 0;
  const char *compile_msg = "";
  if (compileTestCase != NULL) {
    compile_grade = (compiled == 0) ? 100 /*compile_pts*/ : 0;
    compile_msg =
        (compiled == 0) ? "Compiled successfully" : "Compilation failed";
  }
*/
#endif

  bool handle_compilation = (compileTestCase != NULL);

  //total_grade += (readme_grade + compile_grade);

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
    ;
#if 0
            << "\t\t{\n"
            << "\t\t\t\"test_name\": \"README\",\n"
            << "\t\t\t\"points_awarded\": " << 100/*readme_grade*/ << ",\n"
            << "\t\t\t\"message\": \"" << "DUNNO" /*readme_msg*/ << "\",\n"
            << "\t\t},\n";
  if (handle_compilation) {
    json_file << "\t\t{\n"
              << "\t\t\t\"test_name\": \"Compilation\",\n"
              << "\t\t\t\"points_awarded\": " << 100/*compile_grade*/ << ",\n"
              << "\t\t\t\"message\": \"" << compile_msg << "\",\n"
              << "\t\t},\n";
  }
#endif

  json_file << testcase_json.str() << "\t]\n"
            << "}";
  json_file.close();

  return 0;
}
