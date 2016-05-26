#include <sys/types.h>
#include <sys/stat.h>

#include <typeinfo>

#include <cstdio>
#include <cstdlib>
#include <cstring>
#include <cmath>
#include <unistd.h>

#include <iostream>
#include <fstream>
#include <sstream>

#include <vector>
#include <string>
#include <algorithm>

#include "TestCase.h"
#include "default_config.h"


extern std::string GLOBAL_replace_string_before;
extern std::string GLOBAL_replace_string_after;


// =====================================================================
// =====================================================================

int validateTestCases(const std::string &hw_id, const std::string &rcsid, int subnum, const std::string &subtime);
std::string join(std::vector<std::string> strings);

int main(int argc, char *argv[]) {

  std::string hw_id = "";
  std::string rcsid = "";
  int subnum = -1;
  std::string time_of_submission = "";

  /* Check argument usage */
  if (argc == 5) {
    hw_id = argv[1];
    rcsid = argv[2];
    subnum = atoi(argv[3]);
    time_of_submission = argv[4];
  }
  else {
    std::cerr << "VALIDATOR USAGE: validator <hw_id> <rcsid> <submission#> <time-of-submission>" << std::endl;
    return 1;
  } 

  // TODO: add more error checking of arguments


  int rc = validateTestCases(hw_id,rcsid,subnum,time_of_submission);

  if (rc > 0) {
    std::cerr << "Validator terminated" << std::endl;
    return 1;
  }

  return 0;
}





/* Runs through each test case, pulls in the correct files, validates, and outputs the results */
int validateTestCases(const std::string &hw_id, const std::string &rcsid, int subnum, const std::string &subtime) {

  std::string grade_path = ".submit.grade";
  std::ofstream gradefile(grade_path.c_str());

  gradefile << "Grade for: " << rcsid << std::endl;
  gradefile << "  submission#: " << subnum << std::endl;
  int penalty = -std::min(SUBMISSION_PENALTY,int(std::ceil(std::max(0,subnum-MAX_NUM_SUBMISSIONS)/10.0)));
  assert (penalty >= -SUBMISSION_PENALTY && penalty <= 0);
  if (penalty != 0) {
    gradefile << "  penalty for excessive submissions: " << penalty << " points" << std::endl;
  }

  int nonhidden_auto_pts = penalty;
  int hidden_auto_pts = penalty;

  int nonhidden_extra_credit = 0;
  int hidden_extra_credit = 0;

  int nonhidden_possible_pts = 0;
  int hidden_possible_pts = 0;

  int possible_ta_pts = TA_POINTS;

  std::stringstream testcase_json;
  std::vector<std::string> all_testcases;


#ifdef __CUSTOMIZE_AUTO_GRADING_REPLACE_STRING__
  GLOBAL_replace_string_before = __CUSTOMIZE_AUTO_GRADING_REPLACE_STRING__;
  GLOBAL_replace_string_after  = CustomizeAutoGrading(rcsid);
  std::cout << "CUSTOMIZE AUTO GRADING for user '" << rcsid << "'" << std::endl;
  std::cout << "CUSTOMIZE AUTO GRADING replace " <<  GLOBAL_replace_string_before << " with " << GLOBAL_replace_string_after << std::endl;
#endif

  //system ("ls -lta");
  system("find . -type f");

  // LOOP OVER ALL TEST CASES
  for (int i = 0; i < testcases.size(); ++i) {
    std::cout << "------------------------------------------\n" << testcases[i].title() << " - points: " << testcases[i].points() << std::endl;
    
    // START JSON FOR TEST CASE
    std::vector<std::string> testcase_vector;
    testcase_vector.push_back("\t\t\t\"test_name\": \"" + testcases[i].title() + "\"");
    testcase_vector.push_back("\t\t\t\"execute_logfile\": \"" + testcases[i].prefix() + "_execute_logfile.txt\"");

    int testcase_pts = 0;
    std::string message = "";

    // FILE EXISTS & COMPILATION TESTS DON'T HAVE FILE COMPARISONS
    if (testcases[i].isFileExistsTest()) {

      std::cerr << "THIS IS A FILE EXISTS TEST! " << testcases[i].getFilename() << std::endl;
      assert (testcases[i].getFilename() != "");

      if ( access( (std::string("")+testcases[i].getFilename()).c_str(), F_OK|R_OK|W_OK ) != -1 ) { /* file exists */
        std::cerr << "file does exist: " << testcases[i].getFilename() << std::endl;
        testcase_pts = testcases[i].points();
      }
      else {
        std::cerr << "ERROR file DOES NOT exist: " << testcases[i].getFilename() << std::endl;
        message += "Error: " + testcases[i].getFilename() + " was not found!";
      }
    }
    else if (testcases[i].isCompilationTest()) {
      std::cerr << "THIS IS A COMPILATION! " << std::endl;

      if ( access( testcases[i].getFilename().c_str(), F_OK|R_OK|W_OK ) != -1 ) { /* file exists */
        std::cerr << "file does exist: " << testcases[i].getFilename() << std::endl;
        testcase_pts = testcases[i].points();
      }
      else {
        std::cerr << "ERROR file DOES NOT exist: " << testcases[i].getFilename() << std::endl;
        message += "Error: compilation was not successful!";
      }
      if (testcases[i].isCompilationTest()) {
        testcase_vector.push_back("\t\t\t\"compilation_output\": \"" + testcases[i].prefix() + "_STDERR.txt\"");
      }
    }
    else {
      // ALL OTHER TESTS HAVE 1 OR MORE FILE COMPARISONS
      std::vector<std::string> diff_vectors;
      double my_score = 1.0;
      double fraction_sum = 0.0;
      
      for (int j = 0; j < testcases[i].numFileGraders(); j++) {
        std::vector<std::string> diff_vector;

        std::cerr << "comparison #" << j << std::endl;
        std::string helper_message = "";

        TestResults *result = testcases[i].do_the_grading(j,helper_message);

        // PREPARE THE JSON DIFF FILE
        std::stringstream diff_path;
        diff_path << testcases[i].prefix() << "_" << j << "_diff.json";
        std::ofstream diff_stream(diff_path.str().c_str());

        if (result != NULL) {
          // THE GRADE (will be compiled across all comparisons)
          std::cout << "result->getGrade() = " << result->getGrade() << std::endl;
	  assert (result->getGrade() >= 0.0 && result->getGrade() <= 1.0);

          double pts_fraction = testcases[i].test_case_grader_vec[j]->points_fraction;
          std::cout << "pts_fraction = " << pts_fraction << std::endl;

          if (pts_fraction < -0.5) {
            pts_fraction = 1 / double(testcases[i].numFileGraders());
          }
          fraction_sum += pts_fraction;
	  
          my_score -= pts_fraction*(1-result->getGrade());

          std::cout << "my_score = " << my_score << std::endl;

          result->printJSON(diff_stream);
          helper_message += " " + result->get_message();
          // CLEANUP THIS COMPARISON
          delete result;
        }

        // JSON FOR THIS COMPARISON
        diff_vector.push_back("\t\t\t\t\t\"diff_id\":\"" + testcases[i].prefix() + "_" + std::to_string(j) + "_diff\"");

	std::string dm = testcases[i].test_case_grader_vec[j]->display_mode();
	if (dm != "") {
	  diff_vector.push_back("\t\t\t\t\t\"display_mode\":\""+dm+"\"");
	}

        diff_vector.push_back("\t\t\t\t\t\"student_file\":\"" + testcases[i].filename(j) + "\"");

        std::string expected = "";
        if (testcases[i].test_case_grader_vec[j] != NULL) {
          expected = testcases[i].test_case_grader_vec[j]->getExpected();
        }



	//#ifdef __CUSTOMIZE_AUTO_GRADING_REPLACE_STRING__
	if (GLOBAL_replace_string_before != "") {
	  std::cout << "BEFORE " << expected << std::endl;
	  while (1) {
	    int location = expected.find(GLOBAL_replace_string_before);
	    if (location == std::string::npos) 
	      break;
	    expected.replace(location,GLOBAL_replace_string_before.size(),GLOBAL_replace_string_after);
	  }
	  std::cout << "AFTER  " << expected << std::endl;
	}
	//#endif
	



        if (expected != "") {
          std::stringstream expected_path;
          std::string id = hw_id;
          std::string expected_out_dir = "test_output/" + id + "/";
          expected_path << expected_out_dir << expected;
          diff_vector.push_back("\t\t\t\t\t\"instructor_file\":\"" + expected_path.str() + "\"");
          diff_vector.push_back("\t\t\t\t\t\"difference\":\"" + testcases[i].prefix() + "_" + std::to_string(j) + "_diff.json\"");
        }

        diff_vector.push_back("\t\t\t\t\t\"description\": \"" + testcases[i].description(j) + "\"");
        if (helper_message != "") {
          diff_vector.push_back("\t\t\t\t\t\"message\": \"" + helper_message + "\"");
        }
        diff_vectors.push_back("\t\t\t\t{\n" + join(diff_vector) + "\t\t\t\t}");
      } // END COMPARISON LOOP

      testcase_vector.push_back("\t\t\t\"diffs\": [\n" + join(diff_vectors) + "\t\t\t]");

      if (fraction_sum < 0.99 || fraction_sum > 1.01) {
        std::cout << "WARNING: Fraction sum " << fraction_sum << std::endl;
      }
      assert (fraction_sum > 0.99); 
      assert (my_score <= 1.00001);
      my_score = std::max(0.0,std::min(1.0,my_score));
      std::cout << "[ FINISHED ] my_score = " << my_score << std::endl;
      testcase_pts = (int)floor(my_score * testcases[i].points());
    } // end if/else of test case type

    // output grade & message

    std::cout << "Grade: " << testcase_pts << std::endl;

    // TODO: LOGIC NEEDS TO BE TESTED WITH MORE COMPLEX HOMEWORK!

    if (!testcases[i].hidden()) {
      nonhidden_auto_pts += testcase_pts;
      if (testcases[i].extracredit()) {
        nonhidden_extra_credit += testcase_pts; //testcases[i].points();
      }
      else {
        nonhidden_possible_pts += testcases[i].points();
      }
    } 
    hidden_auto_pts += testcase_pts;
    if (testcases[i].extracredit()) {
      hidden_extra_credit += testcase_pts; //testcases[i].points();
    }
    else {
      hidden_possible_pts += testcases[i].points();
    }
    
    testcase_vector.push_back("\t\t\t\"points_awarded\": " + std::to_string(testcase_pts));

    if (message != "") {
      testcase_vector.push_back("\t\t\t\"message\": \"" + message + "\"");
    }

    all_testcases.push_back("\t\t{\n" + join(testcase_vector) + "\t\t}");

    gradefile << "  Test " << std::setw(2) << std::right << i+1 << ":" 
        << std::setw(30) << std::left << testcases[i].just_title() << " " 
        << std::setw(2) << std::right << testcase_pts << " / " 
        << std::setw(2) << std::right << testcases[i].points() << std::endl;

  } // end test case loop

  int total_possible_pts = possible_ta_pts + hidden_possible_pts;

  std::cout << "penalty                 " <<  penalty << std::endl;
  std::cout << "nonhidden auto pts      " <<  nonhidden_auto_pts << std::endl;
  std::cout << "hidden auto pts         " <<  hidden_auto_pts << std::endl;
  std::cout << "nonhidden extra credit  " <<  nonhidden_extra_credit << std::endl;
  std::cout << "hidden extra credit     " <<  hidden_extra_credit << std::endl;
  std::cout << "nonhidden possible pts  " <<  nonhidden_possible_pts << std::endl;
  std::cout << "hidden possible pts     " <<  hidden_possible_pts << std::endl;
  std::cout << "possible ta pts         " <<  possible_ta_pts << std::endl;
  std::cout << "total possible pts      " <<  total_possible_pts << std::endl;

  assert (total_possible_pts == TOTAL_POINTS);

  /* Generate submission.json */
  std::ofstream json_file("submission.json");
  json_file << "{\n"
            << "\t\"submission_number\": " << subnum << ",\n"
            << "\t\"points_awarded\": " << hidden_auto_pts << ",\n"
            << "\t\"nonhidden_points_awarded\": " << nonhidden_auto_pts << ",\n"
	    << "\t\"extra_credit_points_awarded\": " << hidden_extra_credit << ",\n"
	    << "\t\"non_extra_credit_points_awarded\": " << hidden_auto_pts - hidden_extra_credit << ",\n"
            << "\t\"submission_time\": \"" << subtime << "\",\n"
            << "\t\"testcases\": [\n";
  json_file << join(all_testcases) << "\t]\n"
      << "}";
  json_file.close();

  gradefile << "Automatic extra credit (w/o hidden):               " << "+ " << nonhidden_extra_credit << " points" << std::endl;
  gradefile << "Automatic grading total (w/o hidden):              " << nonhidden_auto_pts << " / " << nonhidden_possible_pts << std::endl;
  gradefile << "Max possible hidden automatic grading points:      " << hidden_possible_pts - nonhidden_possible_pts << std::endl;
  gradefile << "Automatic extra credit:                            " << "+ " << hidden_extra_credit << " points" << std::endl;
  gradefile << "Automatic grading total:                           " << hidden_auto_pts << " / " << hidden_possible_pts << std::endl;
  gradefile << "Remaining points to be graded by TA:               " << possible_ta_pts << std::endl;
  gradefile << "Max points for assignment (excluding extra credit):" << total_possible_pts << std::endl;


  return 0;
}

std::string join(std::vector<std::string> strings) {
  std::stringstream ss;
  for (int i = 0; i < strings.size(); i++) {
    if (i == strings.size()-1) {
      ss << strings[i] << "\n";
    }
    else {
      ss << strings[i] << ",\n";
    }
  }
  return ss.str();
}

// =====================================================================
// =====================================================================
