#include <iostream>
#include <fstream>

#include "TestCase.h"
#include "default_config.h"


/*

  Generates a file in json format containing all of the information defined in
  config.json for easier parsing.

*/


// =====================================================================
// =====================================================================

nlohmann::json printTestCase(TestCase test) {
  nlohmann::json j;
  j["title"] = test.title();
  //if (test.details() != "") 
  j["details"] = test.details();
  j["points"] = test.points();
  j["extra_credit"] = test.extra_credit();
  j["hidden"] = false;
  j["visible"] = true;
  //if (test.hidden_points())
  //j["hidden_points"] = true;
  //if (test.getView_file_results())
  j["view_file_results"] = test.getView_file_results();
  j["view_test_points"] = true;
  j["view_file"] = test.getView_file();
  return j;
}

int main(int argc, char *argv[]) {

  nlohmann::json config_json;
  std::stringstream sstr(GLOBAL_config_json_string);
  sstr >> config_json;
  
  nlohmann::json j;

  if (argc != 2) {
    std::cout << "USAGE: " << argv[0] << " [output_file]" << std::endl;
    return 0;
  }
  std::cout << "FILENAME " << argv[0] << std::endl;
  int total_nonec = 0;
  int total_ec = 0;

  int visible = 0;

  std::cout << "config.json.size " << config_json.size() << std::endl;
  nlohmann::json::iterator tc = config_json.find("testcases");
  
  assert (tc != config_json.end());

  nlohmann::json all;
  std::cout << "num test cases" << tc->size() << std::endl;
  for (typename nlohmann::json::iterator itr = tc->begin(); itr != tc->end(); itr++) {
    //std::cout << "TEST CASE " << std::endl;
    int points = itr->value("points",0);
    bool extra_credit = itr->value("extra_credit",false);
    bool hidden = itr->value("hidden",false);
    if (!extra_credit)
      total_nonec += points;
    else
      total_ec += points;
    if (!hidden)
      visible += points;

    TestCase tc = TestCase::MakeTestCase(*itr);
    all.push_back(printTestCase(tc)); 
  }
  std::cout << "processed " << all.size() << " test cases" << std::endl;
  j["num_testcases"] = all.size();
  j["testcases"] = all;
 
  std::string start_red_text = "\033[1;31m";
  std::string end_red_text   = "\033[0m";

  nlohmann::json grading_parameters = config_json.value("grading_parameters",nlohmann::json::object());
  int AUTO_POINTS         = grading_parameters.value("AUTO_POINTS",total_nonec);
  int EXTRA_CREDIT_POINTS = grading_parameters.value("EXTRA_CREDIT_POINTS",total_ec);
  int TA_POINTS           = grading_parameters.value("TA_POINTS",0);
  int TOTAL_POINTS        = grading_parameters.value("TOTAL_POINTS",AUTO_POINTS+TA_POINTS);

  if (total_nonec != AUTO_POINTS) {
    std::cout << "\n" << start_red_text << "ERROR: Automated Points do not match testcases." << total_nonec 
	      << "!=" << AUTO_POINTS << end_red_text << "\n" << std::endl;
    return 1;
  }
  if (total_ec != EXTRA_CREDIT_POINTS) {
    std::cout << "\n" << start_red_text << "ERROR: Extra Credit Points do not match testcases." << total_ec 
	      << "!=" << EXTRA_CREDIT_POINTS << end_red_text << "\n" << std::endl;
    return 1;
  }
  if (total_nonec + TA_POINTS != TOTAL_POINTS) {
    std::cout << "\n" << start_red_text << "ERROR: Automated Points and TA Points do not match total." 
	      << end_red_text << "\n" << std::endl;
    return 1;
  }


  std::string id = getAssignmentIdFromCurrentDirectory(std::string(argv[0]));
  //std::vector<std::string> part_names = PART_NAMES;

  
  j["id"] = id;
  if (config_json.find("assignment_message") != config_json.end()) {
    j["assignment_message"] = config_json.value("assignment_message",""); 
  }
  j["max_submissions"] = MAX_NUM_SUBMISSIONS;
  j["max_submission_size"] = MAX_SUBMISSION_SIZE;

  nlohmann::json::iterator parts = config_json.find("part_names");
  if (parts != config_json.end()) {
    j["num_parts"] = parts->size();
    for (int i = 0; i < parts->size(); i++) {
      j["part_names"].push_back((*parts)[i]);
    }
  }

  j["auto_pts"] = AUTO_POINTS;
  j["points_visible"] = visible;
  j["ta_pts"] = TA_POINTS;
  j["total_pts"] = TOTAL_POINTS;
  

  // =================================================================================
  // EXPORT THE JSON FILE

  std::ofstream init;
  init.open(argv[1], std::ios::out);

  if (!init.is_open()) {
    std::cout << "\n" << start_red_text << "ERROR: unable to open new file for initialization... Now Exiting" 
	      << end_red_text << "\n" << std::endl;
    return 0;
  }

  init << j.dump(4) << std::endl;
  
  return 0;
}
