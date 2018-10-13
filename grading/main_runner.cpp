#include <unistd.h>
#include <cstdlib>
#include <string>
#include <iostream>
#include <cassert>
#include <algorithm>

#include "default_config.h"
#include "execute.h"

#include <limits>

// =====================================================================
// =====================================================================


int main(int argc, char *argv[]) {
  std::cout << "Running User Code..." << std::endl;

  std::string hw_id = "";
  std::string rcsid = "";
  int subnum = -1;
  std::string time_of_submission = "";
  std::string docker_name = "";
  //If test_case_to_run isn't passed in as a parameter, all testcases are run.
  int test_case_to_run = -1;
  // Check command line arguments
  if (argc >= 5) {
    hw_id = argv[1];
    rcsid = argv[2];
    subnum = atoi(argv[3]);
    time_of_submission = argv[4];
    if (argc >= 6){
      test_case_to_run = atoi(argv[5]);
    }
    if(argc >= 7){
      docker_name = argv[6];
      std::cout << "TESTCASE: " << test_case_to_run << "! THE DOCKER NAME THAT WAS PASSED IN: " << docker_name << std::endl;
    }
  }
  else if (argc != 1) {
    std::cerr << "INCORRECT ARGUMENTS TO RUNNER" << std::endl;
    return 1;
  } 

  // LOAD HW CONFIGURATION JSON
  nlohmann::json config_json = LoadAndProcessConfigJSON(rcsid);

  // nlohmann::json grading_parameters = config_json.value("grading_parameters",nlohmann::json::object());
  // int AUTO_POINTS         = grading_parameters.value("AUTO_POINTS",0);
  // int EXTRA_CREDIT_POINTS = grading_parameters.value("EXTRA_CREDIT_POINTS",0);
  // int TA_POINTS           = grading_parameters.value("TA_POINTS",0);
  // int TOTAL_POINTS        = grading_parameters.value("TOTAL_POINTS",AUTO_POINTS+TA_POINTS);

  // necessary since the untrusted user does not have a home directory
  setenv("DYNAMORIO_CONFIGDIR", ".", 1);

  system("find . -type f -exec ls -sh {} +");

  // Run each test case and create output files
  std::vector<std::string> required_capabilities = stringOrArrayOfStrings(config_json, "required_capabilities");
  
  bool windowed = false;
  if (std::find(required_capabilities.begin(), required_capabilities.end(), "windowed") != required_capabilities.end()){
    windowed = true;
  }

  nlohmann::json::iterator tc = config_json.find("testcases");
  assert (tc != config_json.end());

  if(test_case_to_run != -1){
    //testcases begin counting at 1 and end at tc->size()
    assert (test_case_to_run <= tc->size());
  }else{
    std::cout << "Running all testcases in a single run." << std::endl;
  }

  for (unsigned int i = 1; i <= tc->size(); i++) {

    TestCase my_testcase(config_json,i-1,docker_name);

    if (my_testcase.isFileCheck() || my_testcase.isCompilation()){
      continue;
    }

    if(test_case_to_run != -1 &&  test_case_to_run != i){
      continue;
    }

    std::cout << "========================================================" << std::endl;
    std::cout << "TEST #" << i << std::endl;

    std::vector<std::string> commands = my_testcase.getCommands();

    std::vector<nlohmann::json> actions  = mapOrArrayOfMaps((*tc)[i-1],"actions");
    std::vector<nlohmann::json> dispatcher_actions = mapOrArrayOfMaps((*tc)[i-1],"dispatcher_actions");

    assert (commands.size() > 0);

    std::cout << "TITLE " << my_testcase.getTitle() << std::endl;
    
    for (int x = 0; x < commands.size(); x++) {
      std::cout << "COMMAND " << commands[x] << std::endl;

      assert (commands[x] != "MISSING COMMAND");
      assert (commands[x] != "");
      
      std::string which = "";
      if (commands.size() > 1) {
        which = "_" + std::to_string(x);
      }
      
      
      std::string logfile = "execute_logfile.txt";
      // run the command, capturing STDOUT & STDERR
      int exit_no = execute(commands[x]
                            +
                            " 1>" + "STDOUT" + which + ".txt" +
                            " 2>" + "STDERR" + which + ".txt",
                            actions,
                            dispatcher_actions,
                            logfile,
                            my_testcase.get_test_case_limits(),
                            config_json.value("resource_limits",nlohmann::json()),
                            config_json,
                            windowed); 
    }
    std::cout << "========================================================" << std::endl;
    std::cout << "FINISHED TEST #" << i << std::endl;
  }
  return 0;
}

// =====================================================================
// =====================================================================
