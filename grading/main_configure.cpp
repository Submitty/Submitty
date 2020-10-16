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

int main(int argc, char *argv[]) {

  if (argc != 2) {
    std::cout << "USAGE: " << argv[0] << " [output_file]" << std::endl;
    return 0;
  }
  std::cout << "FILENAME " << argv[0] << std::endl;

  // LOAD HW CONFIGURATION JSON
  nlohmann::json config_json = FillInConfigDefaults(argv[0]);  // don't know the username yet

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
