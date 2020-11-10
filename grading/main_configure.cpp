#include <iostream>
#include <fstream>

#include "default_config.h"
#include "load_config_json.h"

// When we compile main_configure, this is the empty string, otherwise, it is
// defined in json_generated.cpp
const char *GLOBAL_config_json_string = "";


/*

  Generates a file in json format containing all of the information defined in
  config.json for easier parsing.

*/

// =====================================================================
// =====================================================================

int main(int argc, char *argv[]) {

  if (argc != 4) {
    std::cout << "USAGE: " << argv[0] << " [input file] [output_file] [assignment_id]" << std::endl;
    return 0;
  }
  std::cout << "FILENAME " << argv[0] << std::endl;

  std::string input_file = argv[1];
  std::string output_file = argv[2];
  std::string assignment_id = argv[3];

  std::ifstream ifs(input_file);
  nlohmann::json instructor_json;
  if (ifs.is_open()) {
    ifs >> instructor_json;
  }

  // LOAD HW CONFIGURATION JSON
  nlohmann::json output_json = FillInConfigDefaults(instructor_json, assignment_id);  // don't know the username yet

  // =================================================================================
  // EXPORT THE JSON FILE

  std::ofstream init;
  init.open(output_file, std::ios::out);
  std::string start_red_text = "\033[1;31m";
  std::string end_red_text   = "\033[0m";
  if (!init.is_open()) {
    std::cout << "\n" << start_red_text << "ERROR: unable to open new file for initialization... Now Exiting"
        << end_red_text << "\n" << std::endl;
    return 0;
  }
  init << output_json.dump(4) << std::endl;
  // -----------------------------------------------------------------------
  // Also, write out the config file with automatic defaults (for debugging)
  std::string complete_config_file = output_file;
  int b_pos = complete_config_file.find("/build/build_");
  // If we are not in the test suite
  if (b_pos != std::string::npos) {
    complete_config_file = complete_config_file.substr(0,b_pos) +
      "/complete_config/complete_config_"+ complete_config_file.substr(b_pos+13,complete_config_file.size()-b_pos-13);
    std::string mkdir_command = "mkdir -p " + complete_config_file.substr(0,b_pos) + "/complete_config/";
    system (mkdir_command.c_str());
    std::ofstream complete_config;
    complete_config.open(complete_config_file, std::ios::out);
    complete_config << output_json.dump(4) << std::endl;
  }
  // If we are in the test suite
  else{
    int b_pos = complete_config_file.find("/data/");
    if (b_pos != std::string::npos) {
      complete_config_file = complete_config_file.substr(0,b_pos) +
        "/assignment_config/complete_config.json";
      std::ofstream complete_config;
      complete_config.open(complete_config_file, std::ios::out);
      complete_config << output_json.dump(4) << std::endl;
    }

  }
  return 0;
}
