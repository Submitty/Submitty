#include <iostream>
#include <fstream>

#include "execute.h"
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
  j["publish_actions"] = test.publishActions();
  j["dispatcher_actions"] = test.getDispatcherActions();
  j["actions"] = test.getGraphicsActions();
  return j;
}

int main(int argc, char *argv[]) {

  if (argc != 2) {
    std::cout << "USAGE: " << argv[0] << " [output_file]" << std::endl;
    return 0;
  }

  // LOAD HW CONFIGURATION JSON
  nlohmann::json config_json = LoadAndProcessConfigJSON("");  // don't know the username yet

  nlohmann::json j;
  j["id"] = getAssignmentIdFromCurrentDirectory(std::string(argv[0]));

  

  std::cout << "FILENAME " << argv[0] << std::endl;
  int total_nonec = 0;
  int total_ec = 0;

  int visible = 0;
  std::cout << "config.json.size " << config_json.size() << std::endl;
  nlohmann::json::iterator testcases = config_json.find("testcases");

  assert (testcases != config_json.end());

  int max_submissions = MAX_NUM_SUBMISSIONS;

  nlohmann::json all;

  int total_time = 0;
  //The base max time per testcase.
  int base_time = default_limits.find(RLIMIT_CPU)->second;

  int leeway = CPU_TO_WALLCLOCK_TIME_BUFFER;

  nlohmann::json::iterator rl = config_json.find("resource_limits");
  if (rl != config_json.end()){
    base_time = rl->value("RLIMIT_CPU", base_time);
  }
  std::cerr << "BASE TIME " << base_time << std::endl;
  for (typename nlohmann::json::iterator itr = testcases->begin(); itr != testcases->end(); itr++) {
    int points = itr->value("points",0);
    bool extra_credit = itr->value("extra_credit",false);
    bool hidden = itr->value("hidden",false);
    std::string test_id = itr->value("testcase_id","");

    assert(test_id != "");
    //Add this textcases worst case time to the total worst case time.
    int cpu_time = base_time;
    nlohmann::json::iterator rl = itr->find("resource_limits");
    if (rl != itr->end()){
      cpu_time = rl->value("RLIMIT_CPU", base_time);
    }

    cpu_time += leeway;
    total_time += cpu_time;

    if (points > 0) {
      if (!extra_credit)
        total_nonec += points;
      else
        total_ec += points;
      if (!hidden)
        visible += points;
    }
    //container name only matters if we try to get the commands for this testcase.
    std::string container_name = "";
    nlohmann::json my_testcase_json = *itr;
    TestCase my_testcase(my_testcase_json, test_id, container_name);
    if (my_testcase.isSubmissionLimit()) {
      max_submissions = my_testcase.getMaxSubmissions();
    }

    all.push_back(printTestCase(my_testcase));
  }
  std::cout << "processed " << all.size() << " test cases" << std::endl;
  j["num_testcases"] = all.size();
  j["testcases"] = all;
  // TODO: For random item pools, compute the average max possible wait time (across pools).
  j["max_possible_grading_time"] = total_time;
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


  

  j["max_submissions"] = max_submissions;
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
  // -----------------------------------------------------------------------
  // Also, write out the config file with automatic defaults (for debugging)
  std::string complete_config_file = argv[1];
  int b_pos = complete_config_file.find("/build/build_");
  // If we are not in the test suite
  if (b_pos != std::string::npos) {
    complete_config_file = complete_config_file.substr(0,b_pos) +
      "/complete_config/complete_config_"+ complete_config_file.substr(b_pos+13,complete_config_file.size()-b_pos-13);
    std::string mkdir_command = "mkdir -p " + complete_config_file.substr(0,b_pos) + "/complete_config/";
    system (mkdir_command.c_str());
    std::ofstream complete_config;
    complete_config.open(complete_config_file, std::ios::out);
    complete_config << config_json.dump(4) << std::endl;
  }
  // If we are in the test suite
  else{
    int b_pos = complete_config_file.find("/data/");
    if (b_pos != std::string::npos) {
      complete_config_file = complete_config_file.substr(0,b_pos) +
        "/assignment_config/complete_config.json";
      std::ofstream complete_config;
      complete_config.open(complete_config_file, std::ios::out);
      complete_config << config_json.dump(4) << std::endl;
    }

  }
  return 0;
}
