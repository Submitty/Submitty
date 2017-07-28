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


void CleanUpMultipleParts() {

  std::cout << "Clean up multiple parts" << std::endl;
  boost::filesystem::path top( boost::filesystem::current_path() );

  // collect the part directories that have files
  std::set<boost::filesystem::path> non_empty_parts;

  // loop over all of the part directories 
  // NOTE: not necessarily sorted.  OS dependent.  Put in std::set to sort!
  boost::filesystem::directory_iterator end_iter;
  for (boost::filesystem::directory_iterator top_itr( top ); top_itr != end_iter; ++top_itr) {
    boost::filesystem::path part_path = top_itr->path();
    if (!is_directory(part_path)) {
      continue;
    }
    std::string path_name = part_path.string();
    if (path_name.find("part") == std::string::npos) {
      continue;
    }

    int count = 0;
    for (boost::filesystem::directory_iterator part_itr( part_path ); part_itr != end_iter; ++part_itr) {
      count++;
    }
    std::cout << "part: " << part_path.string() << " " << count << std::endl;
    if (count > 0) {
      non_empty_parts.insert(part_path);
    }
  }

  if (non_empty_parts.size() > 1) {

    std::cout << "ERROR!  Student submitted to multiple parts in violation of instructions.\nRemoving files from all but first non empty part." << std::endl;

    // collect files to remove
    std::vector<boost::filesystem::path> remove_this;

    std::set<boost::filesystem::path>::iterator itr = non_empty_parts.begin();
    // skip (keep contents of) first part directory
    itr++;
    while (itr != non_empty_parts.end()) {
      for (boost::filesystem::directory_iterator part_itr( *itr ); part_itr != end_iter; ++part_itr) {
        remove_this.push_back(part_itr->path());
      }
      itr++;
    }

    // remove those files
    for (int i = 0; i < remove_this.size(); i++) {
      std::cout << "REMOVE: " << remove_this[i].string() << std::endl;
      boost::filesystem::remove(remove_this[i]);
    }
  }
}


// =====================================================================
// =====================================================================

int main(int argc, char *argv[]) {

  std::cout << "MAIN COMPILE" << std::endl;
  std::vector<std::string> actions;
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


  // if it's a "one part only" assignment, check if student
  // submitted to multiple parts
  bool one_part_only = config_json.value("one_part_only",false);
  if (one_part_only) {
    CleanUpMultipleParts();
  }
  

  // Run each COMPILATION TEST
  nlohmann::json::iterator tc = config_json.find("testcases");
  assert (tc != config_json.end());
  for (unsigned int i = 0; i < tc->size(); i++) {

    std::cout << "========================================================" << std::endl;

    TestCase my_testcase((*tc)[i],config_json);

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
              std::string new_filename = my_testcase.getPrefix() + "_" + files[i];
              //std::string new_filename = my_testcase.getPrefix() + "_" + replace_slash_with_double_underscore(files[i]);
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
                      config_json);
              
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
                              actions,
                              my_testcase.getPrefix() + "_execute_logfile.txt",
                              my_testcase.get_test_case_limits(),
                              config_json.value("resource_limits",nlohmann::json()),
                              config_json);

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

