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

#include "json.hpp"

extern std::string GLOBAL_replace_string_before;
extern std::string GLOBAL_replace_string_after;


// =====================================================================
// =====================================================================

int validateTestCases(const std::string &hw_id, const std::string &rcsid, int subnum, const std::string &subtime);

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

  nlohmann::json config_json;
  std::stringstream sstr(GLOBAL_config_json_string);
  sstr >> config_json;


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

  std::stringstream testcase_json;

  nlohmann::json all_testcases;

#ifdef __CUSTOMIZE_AUTO_GRADING_REPLACE_STRING__
  GLOBAL_replace_string_before = __CUSTOMIZE_AUTO_GRADING_REPLACE_STRING__;
  GLOBAL_replace_string_after  = CustomizeAutoGrading(rcsid);
  std::cout << "CUSTOMIZE AUTO GRADING for user '" << rcsid << "'" << std::endl;
  std::cout << "CUSTOMIZE AUTO GRADING replace " <<  GLOBAL_replace_string_before << " with " << GLOBAL_replace_string_after << std::endl;
#endif

  //system ("ls -lta");
  system("find . -type f");

  // LOOP OVER ALL TEST CASES
  nlohmann::json::iterator tc = config_json.find("testcases");
  assert (tc != config_json.end());
  for (unsigned int i = 0; i < tc->size(); i++) {

    TestCase my_testcase = TestCase::MakeTestCase((*tc)[i]);

    std::string title = "Test " + std::to_string(i+1) + " " + (*tc)[i].value("title","MISSING TITLE");
    int points = (*tc)[i].value("points",0);

    std::cout << "------------------------------------------\n" << title << " - points: " << points << std::endl;
    
    // START JSON FOR TEST CASE

    nlohmann::json tc_j;
    tc_j["test_name"] = title;
    tc_j["execute_logfile"] = my_testcase.prefix() + "_execute_logfile.txt";
    int testcase_pts = 0;
    std::string message = "";

    // FILE EXISTS & COMPILATION TESTS DON'T HAVE FILE COMPARISONS
    if (my_testcase.isFileExistsTest()) {

      std::cerr << "THIS IS A FILE EXISTS TEST! " << my_testcase.getFilename() << std::endl;
      assert (my_testcase.getFilename() != "");

      if ( access( (std::string("")+my_testcase.getFilename()).c_str(), F_OK|R_OK|W_OK ) != -1 ) { /* file exists */
        std::cerr << "file does exist: " << my_testcase.getFilename() << std::endl;
        testcase_pts = my_testcase.points();
      }
      else {
        std::cerr << "ERROR file DOES NOT exist: " << my_testcase.getFilename() << std::endl;
        message += "Error: " + my_testcase.getFilename() + " was not found!";
      }
    }
    else if (my_testcase.isCompilationTest()) {
      std::cerr << "THIS IS A COMPILATION! " << std::endl;

      if ( access( my_testcase.getFilename().c_str(), F_OK|R_OK|W_OK ) != -1 ) { /* file exists */
        std::cerr << "file does exist: " << my_testcase.getFilename() << std::endl;
        // CHECK IF WARNINGS EXIST
        std::ifstream ifstr(my_testcase.prefix() + "_STDERR.txt");
        if(!ifstr) std::cerr << my_testcase.prefix() <<  "_STDERR.txt" << " not open.";
        else if(ifstr.peek() != std::ifstream::traits_type::eof()){
          std::cerr << my_testcase.prefix() <<  "_STDERR.txt" << " not empty.";
          testcase_pts = (int)floor( my_testcase.points()*(1 - my_testcase.get_warning_frac()) );
          message += "Unresolved warnings in your program!";
        }
        else{
          testcase_pts = my_testcase.points();
        }
      }
      else {
        std::cerr << "ERROR file DOES NOT exist: " << my_testcase.getFilename() << std::endl;
        message += "Error: compilation was not successful!";
      }
      if (my_testcase.isCompilationTest()) {
        tc_j["compilation_output"] = my_testcase.prefix() + "_STDERR.txt";
      }
    }
    else {   // ALL OTHER TESTS HAVE 1 OR MORE FILE COMPARISONS
      nlohmann::json autocheck_js;
      double my_score = 1.0;
      for (int j = 0; j < my_testcase.numFileGraders(); j++) {
        std::cerr << "autocheck #" << j << std::endl;
        TestResults *result = my_testcase.do_the_grading(j);
        assert (result != NULL);
        // loop over the student files
        std::vector<std::string> filenames = stringOrArrayOfStrings(my_testcase.test_case_grader_vec[j],"filename");
        for (int FN = 0; FN < filenames.size(); FN++) {
          // JSON FOR THIS COMPARISON
          nlohmann::json autocheck_j; 
          autocheck_j["student_file"] = my_testcase.prefix() + "_" + filenames[FN];
          std::string expected = "";
          expected = my_testcase.test_case_grader_vec[j].value("instructor_file", "");
          if (GLOBAL_replace_string_before != "") {
            while (1) {
              int location = expected.find(GLOBAL_replace_string_before);
              if (location == std::string::npos) break;
              expected.replace(location,GLOBAL_replace_string_before.size(),GLOBAL_replace_string_after);
            }
          }
          std::string autocheckid = std::to_string(j);
          if (filenames.size() > 1) {
            autocheckid += "_" + std::to_string(FN);
            assert (expected == "");
          }
          autocheck_j["autocheck_id"] = my_testcase.prefix() + "_" + autocheckid + "_autocheck";
          //std::string dm = my_testcase.test_case_grader_vec[j].value("display_mode",""); //->display_mode();
          //if (dm != "") { autocheck_j["display_mode"] = dm; }
          if (expected != "") { // PREPARE THE JSON DIFF FILE
            std::stringstream diff_path;
            diff_path << my_testcase.prefix() << "_" << j << "_diff.json";
            std::ofstream diff_stream(diff_path.str().c_str());
            result->printJSON(diff_stream);
            std::stringstream expected_path;
            std::string id = hw_id;
            std::string expected_out_dir = "test_output/" + id + "/";
            expected_path << expected_out_dir << expected;
            autocheck_j["instructor_file"] = expected_path.str();
            autocheck_j["difference"] = my_testcase.prefix() + "_" + std::to_string(j) + "_diff.json";
          }
          autocheck_j["description"] = my_testcase.description(j);
          if (FN==0) {
            for (int m = 0; m < result->getMessages().size(); m++) {
              if (result->getMessages()[m] != "")
                autocheck_j["messages"].push_back(result->getMessages()[m]); 
            }
          }
          autocheck_js.push_back(autocheck_j);
        }
        std::cout << "result->getGrade() = " << result->getGrade() << std::endl;
        assert (result->getGrade() >= 0.0 && result->getGrade() <= 1.0);
        double deduction = my_testcase.test_case_grader_vec[j].value("deduction",1.0); 
        assert (deduction >= -0.001 && deduction <= 1.001);
        std::cout << "deduction multiplier = " << deduction << std::endl;
        my_score -= deduction*(1-result->getGrade());
        std::cout << "my_score = " << my_score << std::endl;
         delete result;
        result = NULL;
      } // END COMPARISON LOOP

      tc_j["autochecks"] = autocheck_js;
      assert (my_score <= 1.00001);
      my_score = std::max(0.0,std::min(1.0,my_score));
      std::cout << "[ FINISHED ] my_score = " << my_score << std::endl;
      testcase_pts = (int)floor(my_score * my_testcase.points());
    } // end if/else of test case type

    // output grade & message

    std::cout << "Grade: " << testcase_pts << std::endl;

    // TODO: LOGIC NEEDS TO BE TESTED WITH MORE COMPLEX HOMEWORK!

    if (!my_testcase.hidden()) {
      nonhidden_auto_pts += testcase_pts;
      if (my_testcase.extra_credit()) {
        nonhidden_extra_credit += testcase_pts; //my_testcase.points();
      }
      else {
        nonhidden_possible_pts += my_testcase.points();
      }
    } 
    hidden_auto_pts += testcase_pts;
    if (my_testcase.extra_credit()) {
      hidden_extra_credit += testcase_pts; //my_testcase.points();
    }
    else {
      hidden_possible_pts += my_testcase.points();
    }
    
    tc_j["points_awarded"] = testcase_pts;
    if (message != "") {
      tc_j["messages"].push_back(message);
    }

    all_testcases.push_back(tc_j); 

    gradefile << "  Test " << std::setw(2) << std::right << i+1 << ":" 
        << std::setw(30) << std::left << my_testcase.just_title() << " " 
        << std::setw(2) << std::right << testcase_pts << " / " 
        << std::setw(2) << std::right << my_testcase.points() << std::endl;

  } // end test case loop


  nlohmann::json grading_parameters = config_json.value("grading_parameters",nlohmann::json::object());
  int AUTO_POINTS         = grading_parameters.value("AUTO_POINTS",hidden_possible_pts);
  int EXTRA_CREDIT_POINTS = grading_parameters.value("EXTRA_CREDIT_POINTS",0);
  int TA_POINTS           = grading_parameters.value("TA_POINTS",0);
  int TOTAL_POINTS        = grading_parameters.value("TOTAL_POINTS",AUTO_POINTS+TA_POINTS);
  
  int possible_ta_pts = TA_POINTS;
  int total_possible_pts = possible_ta_pts + hidden_possible_pts;

  std::cout << "totals " << possible_ta_pts << " " << hidden_possible_pts << " " << TOTAL_POINTS << std::endl;

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
  nlohmann::json sj;
  sj["submission_number"] = subnum;
  sj["points_awarded"] = hidden_auto_pts;
  sj["nonhidden_points_awarded"] = nonhidden_auto_pts;
  sj["extra_credit_points_awarded"] = hidden_extra_credit;
  sj["non_extra_credit_points_awarded"] = hidden_auto_pts - hidden_extra_credit;
  sj["submission_time"] = subtime;
  sj["testcases"] = all_testcases;


  std::ofstream json_file("submission.json");
  json_file << sj.dump(4);


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


// =====================================================================
// =====================================================================
