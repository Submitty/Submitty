#include <unistd.h>
#include <cstdlib>
#include <string>
#include <iostream>
#include <cassert>
#include <algorithm>

#include "default_config.h"
#include "execute.h"



// =====================================================================
// =====================================================================


int main(int argc, char *argv[]) {
  std::cout << "Running User Code..." << std::endl;

  std::string hw_id = "";
  std::string rcsid = "";
  int subnum = -1;
  std::string time_of_submission = "";
  //If test_case_to_run isn't passed in as a parameter, all testcases are run.
  int test_case_to_run = -1;
  // Check command line arguments
  if (argc >= 5) {
    hw_id = argv[1];
    rcsid = argv[2];
    subnum = atoi(argv[3]);
    time_of_submission = argv[4];
    if (argc == 6){
      test_case_to_run = atoi(argv[5]);
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
    assert (test_case_to_run < tc->size());
  }else{
    std::cout << "Running all testcases in a single run." << std::endl;
  }

  for (unsigned int i = 0; i < tc->size(); i++) {

    TestCase my_testcase(config_json,i);

    if (my_testcase.isFileCheck() || my_testcase.isCompilation()){
      continue;
    }

    if(test_case_to_run != -1 &&  test_case_to_run != i){
      continue;
    }

    std::cout << "========================================================" << std::endl;
    std::cout << "TEST #" << i+1 << std::endl;


    std::vector<std::string> commands = stringOrArrayOfStrings((*tc)[i],"command");
    std::vector<nlohmann::json> actions  = mapOrArrayOfMaps((*tc)[i],"actions");
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
      
      
      std::string logfile = my_testcase.getPrefix() + "_execute_logfile.txt";
      // run the command, capturing STDOUT & STDERR
      int exit_no = execute(commands[x] +
                            " 1>" + my_testcase.getPrefix() + "_STDOUT" + which + ".txt" +
                            " 2>" + my_testcase.getPrefix() + "_STDERR" + which + ".txt",
                            actions,
                            logfile,
                            my_testcase.get_test_case_limits(),
                            config_json.value("resource_limits",nlohmann::json()),
                            config_json,
                            windowed); 

    }

    std::vector<std::vector<std::string>> filenames = my_testcase.getFilenames();
    assert (filenames.size() > 0);
    assert (filenames.size() == my_testcase.numFileGraders());
    // rename any key files created by this test case to prepend the test number
    for (int v = 0; v < filenames.size(); v++) {
      assert (filenames[0].size() > 0);
      for (int i = 0; i < filenames[v].size(); i++) {
        std::cout << "main runner " << v << " " << i << std::endl;
        std::string raw_filename = my_testcase.getMyFilename(v,i);
        std::string filename     = my_testcase.getMyPrefixFilename(v,i);
        assert (raw_filename != "");
        if (access( raw_filename.c_str(), F_OK|R_OK|W_OK ) != -1) { // file exists
          std::vector<nlohmann::json> actions;
          execute("/bin/mv "+raw_filename+" "+filename,
                  actions,
                  "/dev/null",
                  my_testcase.get_test_case_limits(),
                  config_json.value("resource_limits",nlohmann::json()),
                  config_json,
                  false);
          std::cout << "RUNNER!  /bin/mv "+raw_filename+" "+filename << std::endl;
        }
      }
    }
    std::cout << "========================================================" << std::endl;
    std::cout << "FINISHED TEST #" << i+1 << std::endl;
  }
  return 0;
}

// =====================================================================
// =====================================================================
