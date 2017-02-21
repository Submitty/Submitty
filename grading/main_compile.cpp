#include <unistd.h>
#include <cstdlib>
#include <string>
#include <iostream>
#include <sstream>
#include <cassert>
#include <set>
#include <fstream>
#include <dirent.h>

#include "TestCase.h"


#include "execute.h"

#include "default_config.h"

#define DIR_PATH_MAX 1000


// =====================================================================
// =====================================================================

int main(int argc, char *argv[]) {

  std::cout << "MAIN COMPILE" << std::endl;

  nlohmann::json config_json;
  std::stringstream sstr(GLOBAL_config_json_string);
  sstr >> config_json;
  AddSubmissionLimitTestCase(config_json);

  std::cout << "JSON PARSED" << std::endl;

  nlohmann::json grading_parameters = config_json.value("grading_parameters",nlohmann::json::object());
  int AUTO_POINTS         = grading_parameters.value("AUTO_POINTS",0);
  int EXTRA_CREDIT_POINTS = grading_parameters.value("EXTRA_CREDIT_POINTS",0);
  int TA_POINTS           = grading_parameters.value("TA_POINTS",0);
  int TOTAL_POINTS        = grading_parameters.value("TOTAL_POINTS",AUTO_POINTS+TA_POINTS);

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
  else if (argc != 1) {
    std::cerr << "INCORRECT ARGUMENTS TO COMPILER" << std::endl;
    return 1;
  } 

  std::cout << "Compiling User Code..." << std::endl;

  system("find . -type f -exec ls -sh {} +");

  CustomizeAutoGrading(rcsid,config_json);

  // Run each COMPILATION TEST
  nlohmann::json::iterator tc = config_json.find("testcases");
  assert (tc != config_json.end());
  for (unsigned int i = 0; i < tc->size(); i++) {

    std::cout << "========================================================" << std::endl;

    TestCase my_testcase((*tc)[i]);

    if (my_testcase.isFileCheck()) {

      if (my_testcase.isSubmissionLimit()) {
        std::cout << "TEST " << i+1 << " IS SUBMISSION LIMIT!" << std::endl;

      } else {
        std::cout << "TEST " << i+1 << " IS FILE CHECK!" << std::endl;

        std::vector<std::vector<std::string>> filenames = my_testcase.getFilenames();
        for (int i = 0; i < filenames.size(); i++) {
          for (int j = 0; j < filenames[i].size(); j++) {
	    std::string pattern = filenames[i][j];
            std::cout << "PATTERN: " << filenames[i][j] << std::endl;
	    bool special_flag = false;
	    if (pattern.size() > 8 && pattern.substr(pattern.size()-8,8) == ".cpp.txt") {
	      pattern = pattern.substr(0,pattern.size()-4);
	      special_flag = true;
	    }
            std::vector<std::string> files;
            wildcard_expansion(files, pattern, std::cout);
            for (int i = 0; i < files.size(); i++) {
              std::cout << "  rescue  FILE #" << i << ": " << files[i] << std::endl;
              std::string new_filename = my_testcase.getPrefix() + "_" + replace_slash_with_double_underscore(files[i]);
              std::string old_filename = escape_spaces(files[i]);
              new_filename = escape_spaces(new_filename);
	      std::cout << new_filename.substr(new_filename.size()-4,4) << std::endl;
	      if (special_flag) {
		new_filename += ".txt";
	      }
              execute ("/bin/cp "+old_filename+" "+new_filename,
                       "/dev/null",
                       my_testcase.get_test_case_limits(),
                       config_json.value("resource_limits",nlohmann::json()));
              
            }
          }
        }
      }



    } else if (my_testcase.isCompilation()) {
      
      assert (my_testcase.numFileGraders() > 0);
      
      std::vector<std::string> commands = my_testcase.getCommands();

      std::cout << "TEST " << i+1 << " IS COMPILATION!" << std::endl;

      assert (commands.size() > 0);
      for (int j = 0; j < commands.size(); j++) {
        std::cout << "COMMAND #" << j << ": " << commands[j] << std::endl;

        std::string which = "";
        if (commands.size() > 1) {
          which = "_" + std::to_string(j);
        }

        // run the command, capturing STDOUT & STDERR
        int exit_no = execute(commands[j] +
                              " 1>" + my_testcase.getPrefix() + "_STDOUT" + which + ".txt" +
                              " 2>" + my_testcase.getPrefix() + "_STDERR" + which + ".txt",
                              my_testcase.getPrefix() + "_execute_logfile.txt",
                              my_testcase.get_test_case_limits(),
                              config_json.value("resource_limits",nlohmann::json()));

        std::cout<< "FINISHED COMMAND, exited with exit_no: "<<exit_no<<std::endl;
      }
    } else {
      std::cout << "TEST " << i+1 << " IS EXECUTION!" << std::endl;
    }
  }
  std::cout << "========================================================" << std::endl;
  std::cout << "FINISHED ALL TESTS" << std::endl;

  system("find . -type f -exec ls -sh {} +");

  return 0;
}

// ----------------------------------------------------------------

