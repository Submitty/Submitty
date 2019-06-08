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

#define DIR_PATH_MAX 1000

// =====================================================================
// =====================================================================

int main(int argc, char *argv[]) {

  std::string hw_id = "";
  std::string rcsid = "";
  int subnum = -1;
  std::string time_of_submission = "";
  int testcase_to_compile = -1;

  TCLAP::CmdLine cmd("Submitty's main compilation program.", ' ', "0.9");
  TCLAP::UnlabeledValueArg<std::string> homework_id_argument("homework_id", "The unique id for this gradeable", true, "", "string" , cmd);
  TCLAP::UnlabeledValueArg<std::string> student_id_argument("student_id", "The unique id for this student", true, "", "string" , cmd);
  TCLAP::UnlabeledValueArg<int> submission_number_argument("submission_number", "The numeric value for this assignment attempt", true, -1, "integer" , cmd);
  TCLAP::UnlabeledValueArg<std::string> submission_time_argument("submission_time", "The time at which this submissionw as made", true, "", "string" , cmd);
  TCLAP::ValueArg<int> testcase_to_compile_argument("t", "testcase", "The testcase to compile. Pass -1 to compile all testcases.", false, -1, "int", cmd);

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
  nlohmann::json config_json = LoadAndProcessConfigJSON(rcsid);

  std::cout << "SOLUTION RUNNER" << std::endl;
  std::vector<nlohmann::json> actions;
  std::vector<nlohmann::json> dispatcher_actions;

  std::cout << "Running Solution Code..." << std::endl;

  system("find . -type f -exec ls -sh {} +");

  // Run each COMPILATION TEST
  nlohmann::json::iterator tc = config_json.find("testcases");
  assert (tc != config_json.end());
  std::cout << "========================================================" << std::endl;
  TestCase my_testcase(config_json,testcase_to_compile - 1,"");
  for (int i = 0; i < my_testcase.numFileGraders(); i++ ){
    std::vector<std::string> outputGeneratorCommandsForValidation = stringOrArrayOfStrings(my_testcase.getGrader(i), "command");
    for (int j = 0; j < outputGeneratorCommandsForValidation.size();  j++){
      int exit_no = execute(outputGeneratorCommandsForValidation[j],
                            true,
                            actions,
                            dispatcher_actions,
                            "execute_logfile.txt",
                            my_testcase.get_test_case_limits(),
                            config_json.value("resource_limits",nlohmann::json()),
                            config_json,
                            false,
                            "");
    }
  }
  system("find . -type f -exec ls -sh {} +");
  std::cout << "========================================================" << std::endl;

  return 0;
}

// ----------------------------------------------------------------

