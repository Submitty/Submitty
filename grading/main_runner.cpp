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

  nlohmann::json config_json;
  std::stringstream sstr(GLOBAL_config_json_string);
  sstr >> config_json;
  
  nlohmann::json grading_parameters = config_json.value("grading_parameters",nlohmann::json::object());
  int AUTO_POINTS         = grading_parameters.value("AUTO_POINTS",0);
  int EXTRA_CREDIT_POINTS = grading_parameters.value("EXTRA_CREDIT_POINTS",0);
  int TA_POINTS           = grading_parameters.value("TA_POINTS",0);
  int TOTAL_POINTS        = grading_parameters.value("TOTAL_POINTS",AUTO_POINTS+TA_POINTS);
  

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

  // necessary since the untrusted user does not have a home directory
  setenv("DYNAMORIO_CONFIGDIR", ".", 1);


#ifdef __CUSTOMIZE_AUTO_GRADING_REPLACE_STRING__
  std::string replace_string_before = __CUSTOMIZE_AUTO_GRADING_REPLACE_STRING__;
  std::string replace_string_after  = CustomizeAutoGrading(rcsid);
  std::cout << "CUSTOMIZE AUTO GRADING for user '" << rcsid << "'" << std::endl;
  std::cout << "CUSTOMIZE AUTO GRADING replace " <<  replace_string_before << " with " << replace_string_after << std::endl;
#endif
  
  system("find . -type f");

  // Run each test case and create output files
  nlohmann::json::iterator tc = config_json.find("testcases");
  assert (tc != config_json.end());
  for (unsigned int i = 0; i < tc->size(); i++) {

    std::cout << "========================================================" << std::endl;
    std::cout << "TEST #" << i+1 << std::endl;

    TestCase my_testcase((*tc)[i]);

    if (my_testcase.isFileCheck()) continue;
    if (my_testcase.isCompilation()) continue;
    std::vector<std::string> commands = stringOrArrayOfStrings((*tc)[i],"command");
    assert (commands.size() > 0);

    std::cout << "TITLE " << my_testcase.getTitle() << std::endl;
    
    for (int x = 0; x < commands.size(); x++) {

      std::cout << "COMMAND " << commands[x] << std::endl;

      assert (commands[x] != "MISSING COMMAND");
      assert (commands[x] != "");


#ifdef __CUSTOMIZE_AUTO_GRADING_REPLACE_STRING__
      std::cout << "BEFORE " << commands[x] << std::endl;
      while (1) {
	int location = commands[x].find(replace_string_before);
	if (location == std::string::npos) 
	  break;
	commands[x].replace(location,replace_string_before.size(),replace_string_after);
      }
      std::cout << "AFTER  " << commands[x] << std::endl;
#endif
      
      
      std::string which = "";
      if (commands.size() > 1) {
        which = "_" + std::to_string(x);
      }
      
      
      std::string logfile = my_testcase.getPrefix() + "_execute_logfile.txt";
      // run the command, capturing STDOUT & STDERR
      int exit_no = execute(commands[x] +
                            " 1>" + my_testcase.getPrefix() + "_STDOUT" + which + ".txt" +
                            " 2>" + my_testcase.getPrefix() + "_STDERR" + which + ".txt",
                            logfile,
                            my_testcase.get_test_case_limits(),
                            config_json.value("resource_limits",nlohmann::json())); 
      
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
          execute ("/bin/mv "+raw_filename+" "+filename,
                   "/dev/null",
                   my_testcase.get_test_case_limits(),
                   config_json.value("resource_limits",nlohmann::json()));
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
