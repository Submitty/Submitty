#include <unistd.h>
#include <cstdlib>
#include <string>
#include <iostream>
#include <cassert>
#include <algorithm>

#include "default_config.h"
#include "execute.h"
#include "TestCase.h"
#include "load_config_json.h"


#include <limits>
#include <tclap/CmdLine.h>

// =====================================================================
// =====================================================================

std::string addRedirectsToCommand(std::string this_command, std::string which){
  // Check to see if the instructor is already capturing STDIN
  if(this_command.find("1>") == std::string::npos){

    bool found_redirect = false;
    size_t position = this_command.find(">");

    // Check to see if they are using > rather than 1>
    // Note: escaped > characters don't count.
    while(position != std::string::npos){
      // Check to see if the character was escaped.
      // If it wasn't, break the loop because it represents a redirect.
      if(this_command[position-1] != '\\'){
        found_redirect=true;
        break;
      }
      position = this_command.find(">",position+1);
    }

    // Append a redirect if there isn't one already.
    if(found_redirect == false){
      this_command = this_command + " 1>STDOUT" + which + ".txt";
    }
  }

  if(this_command.find("2>") == std::string::npos){
    this_command += " 2>STDERR" + which + ".txt";
  }

  return this_command;
}

void executeSetOfCommands(std::vector<std::string> setOfCommands,
                          std::vector<nlohmann::json> actions,
                          std::vector<nlohmann::json> dispatcher_actions,
                          bool windowed,
                          std::string display_variable,
                          TestCase testcase,
                          std::string logfile,
                          nlohmann::json config_json,
                          std::string testcase_to_run){

  if ( setOfCommands.size() > 0 ) {

    std::cout << "========================================================" << std::endl;

    std::cout << "TEST " << testcase_to_run << std::endl;
    std::cout << "TITLE " << testcase.getTitle() << std::endl;

    for (int command_number = 0; command_number < setOfCommands.size();  command_number++){
      std::string command = setOfCommands[command_number];

      assert (command != "MISSING COMMAND");
      assert (command != "");

      std::string which = "";
      if (setOfCommands.size() > 1) {
        which = "_" + std::to_string(command_number);
      }

      command = addRedirectsToCommand(command, which);

      int exit_no = execute(command,
                            actions,
                            dispatcher_actions,
                            logfile,
                            testcase.get_test_case_limits(),
                            config_json.value("resource_limits",nlohmann::json()),
                            config_json,
                            windowed,
                            display_variable,
                            testcase.has_timestamped_stdout());
    }
    std::cout << "========================================================" << std::endl;
    std::cout << "FINISHED TEST " << testcase_to_run << std::endl;
  }
}

int main(int argc, char *argv[]) {
  std::cout << "Running User Code..." << std::endl;
  std::string hw_id = "";
  std::string rcsid = "";
  int subnum = -1;
  std::string time_of_submission = "";
  std::string docker_name = "";
  std::string testcase_to_run = "";
  std::string display_variable = "";
  std::string generation_type = "";

  TCLAP::CmdLine cmd("Submitty's main runner program.", ' ', "0.9");
  TCLAP::UnlabeledValueArg<std::string> homework_id_argument("homework_id", "The unique id for this gradeable", true, "", "string" , cmd);
  TCLAP::UnlabeledValueArg<std::string> student_id_argument("student_id", "The unique id for this student", true, "", "string" , cmd);
  TCLAP::UnlabeledValueArg<int> submission_number_argument("submission_number", "The numeric value for this assignment attempt", true, -1, "integer" , cmd);
  TCLAP::UnlabeledValueArg<std::string> submission_time_argument("submission_time", "The time at which this submission as made", true, "", "string" , cmd);
  TCLAP::UnlabeledValueArg<std::string> testcase_to_run_argument("testcase", "The id of the testcase to run.", true, "", "string", cmd);
  TCLAP::ValueArg<std::string> docker_name_argument("c", "container_name", "The name of the container this attempt is being run in.", false, "", "string", cmd);
  TCLAP::ValueArg<std::string> display_variable_argument("d", "display", "The display to be used for this testcase.", false, "NO_DISPLAY_SET", "string", cmd);
  TCLAP::ValueArg<std::string> generation_type_argument("g", "generation_type", "The type of generation", false, "", "string", cmd);



  //parse arguments.
  try {
    cmd.parse(argc, argv);
    hw_id = homework_id_argument.getValue();
    rcsid = student_id_argument.getValue();
    subnum = submission_number_argument.getValue();
    time_of_submission = submission_time_argument.getValue();
    docker_name = docker_name_argument.getValue();
    testcase_to_run = testcase_to_run_argument.getValue();
    display_variable = display_variable_argument.getValue();
    generation_type = generation_type_argument.getValue();
  }
  catch (TCLAP::ArgException &e)  // catch any exceptions
  {
    std::cerr << "INCORRECT ARGUMENTS TO RUNNER" << std::endl;
    std::cerr << "error: " << e.error() << " for arg " << e.argId() << std::endl;
    return 1;
  }

  // LOAD HW CONFIGURATION JSON
  nlohmann::json config_json = LoadAndCustomizeConfigJson(rcsid);

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
  assert (testcase_to_run != "");


  nlohmann::json testcase_config;
  try {
    testcase_config = find_testcase_by_id(config_json, testcase_to_run);
  } catch(char* c) {
    std::cout << c << std::endl;
    return 1;
  }
  TestCase my_testcase(testcase_config, testcase_to_run, docker_name);


  if (my_testcase.isFileCheck() || my_testcase.isCompilation()){
    return 0;
  }

  std::vector<std::string> commands;
  std::vector<nlohmann::json> actions;
  std::vector<nlohmann::json> dispatcher_actions;

  if ( generation_type == "input" ) {
    commands = my_testcase.getInputGeneratorCommands();
  } else {
    commands = (generation_type == "output" ? my_testcase.getSolutionCommands() : my_testcase.getCommands());
    actions  = mapOrArrayOfMaps(testcase_config, "actions");
    dispatcher_actions = mapOrArrayOfMaps(testcase_config, "dispatcher_actions");
  }

  executeSetOfCommands(commands, actions, dispatcher_actions, windowed, display_variable, my_testcase, "execute_logfile.txt", config_json, testcase_to_run);

  return 0;
}

// =====================================================================
// =====================================================================
