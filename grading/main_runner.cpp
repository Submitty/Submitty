#include <unistd.h>
#include <cstdlib>
#include <string>
#include <iostream>
#include <cassert>

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

  // Check command line arguments
  if (argc == 5) {
    hw_id = argv[1];
    rcsid = argv[2];
    subnum = atoi(argv[3]);
    time_of_submission = argv[4];
  }
  else if (argc != 1) {
    std::cerr << "INCORRECT ARGUMENTS TO RUNNER" << std::endl;
    return 1;
  } 

  // LOAD HW CONFIGURATION JSON
  nlohmann::json config_json = LoadAndProcessConfigJSON(rcsid);

  nlohmann::json grading_parameters = config_json.value("grading_parameters",nlohmann::json::object());
  int AUTO_POINTS         = grading_parameters.value("AUTO_POINTS",0);
  int EXTRA_CREDIT_POINTS = grading_parameters.value("EXTRA_CREDIT_POINTS",0);
  int TA_POINTS           = grading_parameters.value("TA_POINTS",0);
  int TOTAL_POINTS        = grading_parameters.value("TOTAL_POINTS",AUTO_POINTS+TA_POINTS);

  // necessary since the untrusted user does not have a home directory
  setenv("DYNAMORIO_CONFIGDIR", ".", 1);

  system("find . -type f -exec ls -sh {} +");

  // Run each test case and create output files
  nlohmann::json::iterator tc = config_json.find("testcases");
  assert (tc != config_json.end());
  for (unsigned int i = 0; i < tc->size(); i++) {

    std::cout << "========================================================" << std::endl;
    std::cout << "TEST #" << i+1 << std::endl;

    TestCase my_testcase(config_json,i);

    if (my_testcase.isFileCheck()) continue;
    if (my_testcase.isCompilation()) continue;
    std::vector<std::string> commands = stringOrArrayOfStrings((*tc)[i],"command");
    std::vector<std::string> actions  = stringOrArrayOfStrings((*tc)[i],"actions");
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
                            config_json); 
      
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
          std::vector<std::string> actions;
          execute("/bin/mv "+raw_filename+" "+filename,
                  actions,
                  "/dev/null",
                  my_testcase.get_test_case_limits(),
                  config_json.value("resource_limits",nlohmann::json()),
                  config_json);
          std::cout << "RUNNER!  /bin/mv "+raw_filename+" "+filename << std::endl;
        }
      }
    }
  }
  
  std::cout << "========================================================" << std::endl;
  std::cout << "FINISHED ALL TESTS" << std::endl;
  
  return 0;
}

// =====================================================================
// =====================================================================
