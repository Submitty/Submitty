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
#include "execute.h"
#include "json.hpp"

extern std::string GLOBAL_replace_string_before;
extern std::string GLOBAL_replace_string_after;


// =====================================================================
// =====================================================================

int validateTestCases(const std::string &hw_id, const std::string &rcsid, int subnum, const std::string &subtime);

int main(int argc, char *argv[]) {

  // PARSE ARGUMENTS
  if (argc != 5) {
    std::cerr << "VALIDATOR USAGE: validator <hw_id> <rcsid> <submission#> <time-of-submission>" << std::endl;
    return 1;
  }
  std::string hw_id = argv[1];
  std::string rcsid = argv[2];
  int subnum = atoi(argv[3]);
  std::string time_of_submission = argv[4];

  // TODO: add more error checking of arguments

  int rc = validateTestCases(hw_id,rcsid,subnum,time_of_submission);
  if (rc > 0) {
    std::cerr << "Validator terminated" << std::endl;
    return 1;
  }

  return 0;
}


double ValidateGrader(const TestCase &my_testcase, int which_grader,
                      nlohmann::json &autocheck_js, const std::string &hw_id) {

  std::cerr << "autocheck #" << which_grader << std::endl;
  TestResults* result = my_testcase.do_the_grading(which_grader);
  assert (result != NULL);

  //my_testcase.debugJSON();

  // loop over the student files
  const nlohmann::json& tcg = my_testcase.getGrader(which_grader);

  float grade = result->getGrade();
  std::cout << "result->getGrade() = " << grade << std::endl;
  assert (grade >= 0.0 && grade <= 1.0);
  double deduction = tcg.value("deduction",1.0);
  assert (deduction >= -0.001 && deduction <= 1.001);
  std::cout << "deduction multiplier = " << deduction << std::endl;
  double score = deduction*(1-grade);
  std::cout << "score = " << score << std::endl;

  std::vector<std::string> filenames = stringOrArrayOfStrings(tcg,"actual_file");
  for (int FN = 0; FN < filenames.size(); FN++) {

    // JSON FOR THIS FILE DISPLAY
    nlohmann::json autocheck_j;
    autocheck_j["description"] = tcg.value("description",filenames[FN]);

    if (my_testcase.isCompilation() && autocheck_j.value("description","") == "executable created") {
      // SKIPPING BECAUSE ITS A COMPILATION
      continue;
    }

    if (my_testcase.isCompilation() && filenames[FN].find("STDERR") != std::string::npos) {
      // FIXME might want to do something different here?
    }

    std::string autocheckid = std::to_string(which_grader);
    if (filenames.size() > 1) {
      autocheckid += "_" + std::to_string(FN);
    }
    autocheck_j["autocheck_id"] = my_testcase.getPrefix() + "_" + autocheckid + "_autocheck";
    std::string student_file = my_testcase.getPrefix() + "_" + filenames[FN];


    student_file = replace_slash_with_double_underscore(student_file);
    std::vector<std::string> files;
    wildcard_expansion(files, student_file, std::cout);
    for (int i = 0; i < files.size(); i++) {
      student_file = files[i];
    }


    bool studentFileExists, studentFileEmpty;
    bool expectedFileExists=false, expectedFileEmpty=false;
    fileStatus(student_file, studentFileExists,studentFileEmpty);

    std::string expected;

    if (studentFileExists) {
      autocheck_j["actual_file"] = student_file;
      expected = tcg.value("expected_file", "");
      if (expected != "") {
        fileStatus(expected, expectedFileExists,expectedFileEmpty);
        assert (expectedFileExists);
        // PREPARE THE JSON DIFF FILE
        std::stringstream diff_path;
        diff_path << my_testcase.getPrefix() << "_" << which_grader << "_diff.json";
        std::ofstream diff_stream(diff_path.str().c_str());
        result->printJSON(diff_stream);
        std::stringstream expected_path;
        std::string id = hw_id;
        std::string expected_out_dir = "test_output/" + id + "/";
        expected_path << expected_out_dir << expected;
        autocheck_j["expected_file"] = expected_path.str();
        autocheck_j["difference_file"] = my_testcase.getPrefix() + "_" + std::to_string(which_grader) + "_diff.json";
      }
    }

    if (FN==0) {
      for (int m = 0; m < result->getMessages().size(); m++) {
        if (result->getMessages()[m] != "")
          autocheck_j["messages"].push_back(result->getMessages()[m]);
      }
    }

    std::cout << "AUTOCHECK GRADE " << grade << std::endl;
    std::cout << "MESSAGES SIZE " << result->getMessages().size() << std::endl;
    std::cout << "STUDENT FILEEXISTS " << studentFileExists << " EMPTY " << studentFileEmpty << std::endl;
    std::cout << "EXPECTED FILEEXISTS " << expectedFileExists << " EMPTY " << expectedFileEmpty << std::endl;

    std::cout << "-----" << std::endl;
    system (("ls -lta " + student_file).c_str());
    std::cout << "-----" << std::endl;
    system (("more " + student_file).c_str());
    std::cout << "-----" << std::endl;
    if (expected != "") {
      system (("ls -lta " + expected).c_str());
      std::cout << "-----" << std::endl;
      system (("more " + expected).c_str());
      std::cout << "-----" << std::endl;
    }

    if (grade < 1.0 ||
        result->getMessages().size() > 0 ||
        (studentFileExists && !studentFileEmpty) ) {
      std::cout << "GOING TO OUTPUT" << std::endl;
      autocheck_js.push_back(autocheck_j);
    }
  }
  delete result;
  return score;
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
  assert (penalty <= 0 && penalty >= -10);

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

    std::cout << "------------------------------------------\n";
    std::cout << "Test # " + std::to_string(i+1) << std::endl;

    TestCase my_testcase((*tc)[i]);
    std::string title = "Test " + std::to_string(i+1) + " " + (*tc)[i].value("title","MISSING TITLE");
    int points = (*tc)[i].value("points",0);
    std::cout << title << " - points: " << points << std::endl;
    
    nlohmann::json tc_j;
    tc_j["test_name"] = title;
    nlohmann::json autocheck_js;

    bool fileExists, fileEmpty;
    fileStatus(my_testcase.getPrefix() + "_execute_logfile.txt", fileExists,fileEmpty);
    if (fileExists && !fileEmpty) {
      nlohmann::json autocheck_j;
      autocheck_j["autocheck_id"] = my_testcase.getPrefix() + "_execute_logfile_autocheck";
      autocheck_j["actual_file"] = my_testcase.getPrefix() + "_execute_logfile.txt";
      autocheck_j["description"] = "Execution Logfile";
      autocheck_js.push_back(autocheck_j);
    }

    int testcase_pts = 0;
    //std::string message = "";
    //message += "Unresolved warnings in your program!";
    //message += "Error: compilation was not successful!";

    double my_score = 1.0;
    assert (my_testcase.numFileGraders() > 0);
    for (int j = 0; j < my_testcase.numFileGraders(); j++) {
      my_score -= ValidateGrader(my_testcase, j, autocheck_js,hw_id);
    }
    if (autocheck_js.size() > 0) {
      tc_j["autochecks"] = autocheck_js;
    }
    assert (my_score <= 1.00001);
    my_score = std::max(0.0,std::min(1.0,my_score));
    std::cout << "[ FINISHED ] my_score = " << my_score << std::endl;
    testcase_pts = (int)floor(my_score * my_testcase.getPoints());

    // output grade & message

    std::cout << "Grade: " << testcase_pts << std::endl;
    // TODO: LOGIC NEEDS TO BE TESTED WITH MORE COMPLEX HOMEWORK!

    if (!my_testcase.getHidden()) {
      nonhidden_auto_pts += testcase_pts;
      if (my_testcase.getExtraCredit()) {
        nonhidden_extra_credit += testcase_pts; 
      }
      else {
        nonhidden_possible_pts += my_testcase.getPoints();
      }
    } 
    hidden_auto_pts += testcase_pts;
    if (my_testcase.getExtraCredit()) {
      hidden_extra_credit += testcase_pts; 
    }
    else {
      hidden_possible_pts += my_testcase.getPoints();
    }
    
    tc_j["points_awarded"] = testcase_pts;
    //if (message != "") {
    //  tc_j["messages"].push_back(message);
    //}
    all_testcases.push_back(tc_j); 
    gradefile << "  Test " << std::setw(2) << std::right << i+1 << ":" 
        << std::setw(30) << std::left << my_testcase.getTitle() << " " 
        << std::setw(2) << std::right << testcase_pts << " / " 
        << std::setw(2) << std::right << my_testcase.getPoints() << std::endl;

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
