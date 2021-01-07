#include <unistd.h>
#include <cstdlib>
#include <string>
#include <iostream>
#include <sstream>
#include <cassert>
#include <set>
#include <fstream>
#include <dirent.h>
#include <tclap/CmdLine.h>

#include "TestCase.h"
#include "execute.h"
#include "default_config.h"
#include "load_config_json.h"

#define DIR_PATH_MAX 1000

// =====================================================================
// =====================================================================

int main(int argc, char *argv[]) {

  std::string hw_id = "";
  std::string rcsid = "";
  int subnum = -1;
  std::string time_of_submission = "";
  std::string testcase_to_compile = "";

  TCLAP::CmdLine cmd("Submitty's main compilation program.", ' ', "0.9");
  TCLAP::UnlabeledValueArg<std::string> homework_id_argument("homework_id", "The unique id for this gradeable", true, "", "string" , cmd);
  TCLAP::UnlabeledValueArg<std::string> student_id_argument("student_id", "The unique id for this student", true, "", "string" , cmd);
  TCLAP::UnlabeledValueArg<int> submission_number_argument("submission_number", "The numeric value for this assignment attempt", true, -1, "integer" , cmd);
  TCLAP::UnlabeledValueArg<std::string> submission_time_argument("submission_time", "The time at which this submissionw as made", true, "", "string" , cmd);
  TCLAP::UnlabeledValueArg<std::string> testcase_to_compile_argument("testcase", "The testcase to compile. Pass -1 to compile all testcases.", true, "", "string", cmd);

  //parse arguments.
  try {
    cmd.parse(argc, argv);
    hw_id = homework_id_argument.getValue();
    rcsid = student_id_argument.getValue();
    subnum = submission_number_argument.getValue();
    time_of_submission = submission_time_argument.getValue();
    testcase_to_compile = testcase_to_compile_argument.getValue();

    std::cout << "hw_id " << hw_id << std::endl;
    std::cout << "rcsid " << rcsid << std::endl;
    std::cout << "subnum " << subnum << std::endl;
    std::cout << "time_of_submission " << time_of_submission << std::endl;
    std::cout << "testcase_to_compile " << testcase_to_compile << std::endl;
  }
  catch (TCLAP::ArgException &e)  // catch any exceptions
  {
    std::cerr << "INCORRECT ARGUMENTS TO COMPILER" << std::endl;
    std::cerr << "error: " << e.error() << " for arg " << e.argId() << std::endl;
    return 1;
  }


  // LOAD HW CONFIGURATION JSON
  nlohmann::json config_json = LoadAndCustomizeConfigJson(rcsid);

  std::cout << "MAIN COMPILE" << std::endl;
  std::vector<nlohmann::json> actions;
  std::vector<nlohmann::json> dispatcher_actions;

  nlohmann::json grading_parameters = config_json.value("grading_parameters",nlohmann::json::object());
  int AUTO_POINTS         = grading_parameters.value("AUTO_POINTS",0);
  int EXTRA_CREDIT_POINTS = grading_parameters.value("EXTRA_CREDIT_POINTS",0);
  int TA_POINTS           = grading_parameters.value("TA_POINTS",0);
  int TOTAL_POINTS        = grading_parameters.value("TOTAL_POINTS",AUTO_POINTS+TA_POINTS);

  std::cout << "Compiling User Code..." << std::endl;

  system("find . -type f -exec ls -sh {} +");
  assert (config_json.find("testcases") != config_json.end());

  nlohmann::json testcase_config;
  try {
    testcase_config = find_testcase_by_id(config_json, testcase_to_compile);
  } catch(char* c) {
    std::cerr << "ERROR: " << c << std::endl;
    return 1;
  }
  TestCase my_testcase(testcase_config, testcase_to_compile, "");

  // Run the compilation test
  std::cout << "========================================================" << std::endl;

  if (my_testcase.isFileCheck()) {

    if (my_testcase.isSubmissionLimit()) {
      std::cout << "TEST " << testcase_to_compile << " IS SUBMISSION LIMIT!" << std::endl;

    } else {
      std::cout << "TEST " << testcase_to_compile << " IS FILE CHECK!" << std::endl;

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
                    dispatcher_actions,
                    "/dev/null",
                    my_testcase.get_test_case_limits(),
                    config_json.value("resource_limits",nlohmann::json()),
                    config_json,
                    false,
                    "",
                    my_testcase.has_timestamped_stdout());
          }
        }
      }
    }
  } else if (my_testcase.isCompilation()) {

    assert (my_testcase.numFileGraders() > 0);

    std::vector<std::string> commands = my_testcase.getCommands();

    std::cout << "TEST " << testcase_to_compile << " IS COMPILATION!" << std::endl;

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
                            dispatcher_actions,
                            "execute_logfile.txt",
                            my_testcase.get_test_case_limits(),
                            config_json.value("resource_limits",nlohmann::json()),
                            config_json,
                            false,
                            "",
                            my_testcase.has_timestamped_stdout());

      std::cout<< "FINISHED COMMAND, exited with exit_no: "<<exit_no<<std::endl;
    }
  } else {
    std::cout << "TEST " << testcase_to_compile << " IS EXECUTION!" << std::endl;
  }
  std::cout << "========================================================" << std::endl;
  std::cout << "FINISHED ALL TESTS" << std::endl;

  system("find . -type f -exec ls -sh {} +");

  return 0;
}

// ----------------------------------------------------------------

