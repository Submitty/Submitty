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

#include "boost/filesystem/operations.hpp"
#include "boost/filesystem/path.hpp"


// =====================================================================
// =====================================================================

int main(int argc, char *argv[]) {

  std::string hw_id = "";
  std::string rcsid = "";
  int subnum = -1;
  std::string time_of_submission = "";
  int testcase_to_compile = -1;

  /* Check argument usage */
  if (argc == 6) {
    hw_id = argv[1];
    rcsid = argv[2];
    subnum = atoi(argv[3]);
    time_of_submission = argv[4];
    testcase_to_compile = atoi(argv[5]);
  }
  else if (argc != 1) {
    std::cerr << "INCORRECT ARGUMENTS TO COMPILER" << std::endl;
    return 1;
  }

  // LOAD HW CONFIGURATION JSON
  nlohmann::json config_json = LoadAndProcessConfigJSON(rcsid);

  std::cout << "MAIN COMPILE" << std::endl;
  std::vector<nlohmann::json> actions;

  nlohmann::json grading_parameters = config_json.value("grading_parameters",nlohmann::json::object());
  int AUTO_POINTS         = grading_parameters.value("AUTO_POINTS",0);
  int EXTRA_CREDIT_POINTS = grading_parameters.value("EXTRA_CREDIT_POINTS",0);
  int TA_POINTS           = grading_parameters.value("TA_POINTS",0);
  int TOTAL_POINTS        = grading_parameters.value("TOTAL_POINTS",AUTO_POINTS+TA_POINTS);

  std::cout << "Compiling User Code..." << std::endl;

  system("find . -type f -exec ls -sh {} +");

  // Run each COMPILATION TEST
  nlohmann::json::iterator tc = config_json.find("testcases");
  assert (tc != config_json.end());
  for (unsigned int i = 1; i <= tc->size(); i++) {

    //Compilation steps must not have a docker name.
    std::string container_name = "";
    TestCase my_testcase(config_json,i-1,container_name);

    if(testcase_to_compile != i){
      continue;
    }
    
    std::cout << "========================================================" << std::endl;

    if (my_testcase.isFileCheck()) {

      if (my_testcase.isSubmissionLimit()) {
        std::cout << "TEST " << i << " IS SUBMISSION LIMIT!" << std::endl;

      } else {
        std::cout << "TEST " << i << " IS FILE CHECK!" << std::endl;

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
              std::string new_filename = my_testcase.getPrefix() + files[i];
              //std::string new_filename = my_testcase.getPrefix() + replace_slash_with_double_underscore(files[i]);
              if (new_filename.substr(new_filename.size() - 4,4) == ".cpp" && !special_flag) {
                new_filename += ".txt";
              }
              std::string old_filename = escape_spaces(files[i]);
              new_filename = escape_spaces(new_filename);
              std::cout << new_filename.substr(new_filename.size()-4,4) << std::endl;
              if (special_flag) {
                new_filename += ".txt";
              }
              execute("/bin/cp "+old_filename+" "+new_filename,
                      actions,
                      "/dev/null",
                      my_testcase.get_test_case_limits(),
                      config_json.value("resource_limits",nlohmann::json()),
                      config_json,
                      false);
            }
          }
        }
      }



    } else if (my_testcase.isCompilation()) {
      
      assert (my_testcase.numFileGraders() > 0);
      
      std::vector<std::string> commands = my_testcase.getCommands();

      std::cout << "TEST " << i << " IS COMPILATION!" << std::endl;

      assert (commands.size() > 0);
      for (int j = 0; j < commands.size(); j++) {
        std::cout << "COMMAND #" << j << ": " << commands[j] << std::endl;

        std::string which = "";
        if (commands.size() > 1) {
          which = "_" + std::to_string(j);
        }

        // run the command, capturing STDOUT & STDERR
        int exit_no = execute(commands[j] +
                              " 1> STDOUT" + which + ".txt" +
                              " 2> STDERR" + which + ".txt",
                              actions,
                              "execute_logfile.txt",
                              my_testcase.get_test_case_limits(),
                              config_json.value("resource_limits",nlohmann::json()),
                              config_json,
                              false);

        std::cout<< "FINISHED COMMAND, exited with exit_no: "<<exit_no<<std::endl;
      }
    } else {
      std::cout << "TEST " << i << " IS EXECUTION!" << std::endl;
    }
  }
  std::cout << "========================================================" << std::endl;
  std::cout << "FINISHED ALL TESTS" << std::endl;

  system("find . -type f -exec ls -sh {} +");

  return 0;
}

// ----------------------------------------------------------------

