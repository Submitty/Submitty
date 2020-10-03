#include <unistd.h>
#include <cstdlib>
#include <string>
#include <iostream>
#include <cassert>
#include <algorithm>
#include <ctype.h>


#include "TestCase.h"
#include "load_config_json.h"
#include "execute.h"

extern const char *GLOBAL_config_json_string;  // defined in json_generated.cpp

void AddAutogradingConfiguration(nlohmann::json &whole_config) {

  if (whole_config["autograding"].find("submission_to_compilation") == whole_config["autograding"].end()) {
    whole_config["autograding"]["submission_to_compilation"].push_back("**/*.cpp");
    whole_config["autograding"]["submission_to_compilation"].push_back("**/*.cxx");
    whole_config["autograding"]["submission_to_compilation"].push_back("**/*.c");
    whole_config["autograding"]["submission_to_compilation"].push_back("**/*.h");
    whole_config["autograding"]["submission_to_compilation"].push_back("**/*.hpp");
    whole_config["autograding"]["submission_to_compilation"].push_back("**/*.hxx");
    whole_config["autograding"]["submission_to_compilation"].push_back("**/*.java");
  }

  if (whole_config["autograding"].find("submission_to_runner") == whole_config["autograding"].end()) {
    whole_config["autograding"]["submission_to_runner"].push_back("**/*.py");
    whole_config["autograding"]["submission_to_runner"].push_back("**/*.pdf");
  }

  if (whole_config["autograding"].find("compilation_to_runner") == whole_config["autograding"].end()) {
    whole_config["autograding"]["compilation_to_runner"].push_back("**/*.out");
    whole_config["autograding"]["compilation_to_runner"].push_back("**/*.class");
  }

  if (whole_config["autograding"].find("compilation_to_validation") == whole_config["autograding"].end()) {
    whole_config["autograding"]["compilation_to_validation"].push_back("test*/STDOUT*.txt");
    whole_config["autograding"]["compilation_to_validation"].push_back("test*/STDERR*.txt");
  }

  if (whole_config["autograding"].find("submission_to_validation") == whole_config["autograding"].end()) {
    whole_config["autograding"]["submission_to_validation"].push_back("**/README.txt");
    whole_config["autograding"]["submission_to_validation"].push_back("input_*.txt");
    whole_config["autograding"]["submission_to_validation"].push_back("**/*.pdf");
    whole_config["autograding"]["submission_to_validation"].push_back(".user_assigment_access.json");
  }

  if (whole_config["autograding"].find("work_to_details") == whole_config["autograding"].end()) {
    whole_config["autograding"]["work_to_details"].push_back("test*/*.txt");
    whole_config["autograding"]["work_to_details"].push_back("test*/*_diff.json");
    whole_config["autograding"]["work_to_details"].push_back("**/README.txt");
    whole_config["autograding"]["work_to_details"].push_back("input_*.txt");
    //todo check up on how this works.
    whole_config["autograding"]["work_to_details"].push_back("test*/input_*.txt");
    // archive the timestamped dispatcher actions
    whole_config["autograding"]["work_to_details"].push_back("**/dispatched_actions.txt");
  }

  if (whole_config["autograding"].find("use_checkout_subdirectory") == whole_config["autograding"].end()) {
    whole_config["autograding"]["use_checkout_subdirectory"] = "";
  }
}

/**
* Add global booleans and configuration options to the configuration
**/
void AddGlobalDefaults(nlohmann::json &whole_config) {

  /*************************************************
  * Add Global File Copy Commands
  **************************************************/
  AddAutogradingConfiguration(whole_config);

  /*************************************************
  * Add Global Booleans
  **************************************************/
  if (!whole_config["timestamped_stdout"].is_boolean()) {
    whole_config["timestamped_stdout"] = false;
  }

  if (!whole_config["publish_actions"].is_boolean()) {
    whole_config["publish_actions"] = false;
  }

  /*************************************************
  * Global Docker Configuration
  **************************************************/

  if (!whole_config["autograding_method"].is_string()) {
    whole_config["autograding_method"] = "jailed_sandbox";
  }else{
    assert(whole_config["autograding_method"] == "docker"
        || whole_config["autograding_method"]  == "jailed_sandbox");
  }

  //check if there are global container defaults present. If not, add an empty object.
  if(whole_config["container_options"].is_null()){
    whole_config["container_options"] = nlohmann::json::object();
  }

  //Fill in the defaults.
  if(!whole_config["container_options"]["container_image"].is_string()){
    whole_config["container_options"]["container_image"] = "submitty/autograding-default:latest";
  }

  if (!whole_config["container_options"]["use_router"].is_boolean()){
    whole_config["container_options"]["use_router"] = false;
  }

  if (!whole_config["container_options"]["single_port_per_container"].is_boolean()){
    whole_config["container_options"]["single_port_per_container"] = false; // connection
  }

  if (!whole_config["container_options"]["number_of_ports"].is_number_integer()){
    whole_config["container_options"]["number_of_ports"] = 1; // connection
  }

}


// This function ensures that any file explicitly tagged as an
// executable in a compilation step is validated and copied
// to each testcase directory.
void PreserveCompiledFiles(nlohmann::json& testcases, nlohmann::json &whole_config) {

  // Loop over all of the test cases
  int which_testcase = 0;
  for (nlohmann::json::iterator my_testcase = testcases.begin();
       my_testcase != testcases.end(); my_testcase++,which_testcase++) {

    std::string testcase_type = my_testcase->value("type","Execution");
    //Skip non compilation tests
    if(testcase_type != "Compilation"){
      continue;
    }

    std::vector<std::string> executable_names = stringOrArrayOfStrings(*my_testcase,"executable_name");
    // Add all executables to compilation_to_runner and compilation_to_validation
    for(std::vector<std::string>::iterator exe = executable_names.begin(); exe != executable_names.end(); exe++){
      std::stringstream ss;
      ss << "test" << std::setfill('0') << std::setw(2) << which_testcase+1 << "/" << *exe;
      std::string executable_name = ss.str();

      if(whole_config["autograding"]["compilation_to_runner"].find("executable_name") == whole_config["autograding"]["compilation_to_runner"].end()){
        whole_config["autograding"]["compilation_to_runner"].push_back(executable_name);
      }

      if(whole_config["autograding"]["compilation_to_validation"].find("executable_name") == whole_config["autograding"]["compilation_to_validation"].end()){
         whole_config["autograding"]["compilation_to_validation"].push_back(executable_name);
      }
    }
  }
}

// This function will automatically archive all non-executable files
// that are validated.  This ensures that the web viewers will have
// the necessary files to display the results to students and graders.
void ArchiveValidatedFiles(nlohmann::json &testcases, nlohmann::json &whole_config) {

  // FIRST, loop over all of the test cases
  int which_testcase = 0;
  for (nlohmann::json::iterator my_testcase = testcases.begin();
       my_testcase != testcases.end(); my_testcase++,which_testcase++) {


    nlohmann::json::iterator validators = my_testcase->find("validation");
    if (validators == my_testcase->end()) { /* no autochecks */ continue; }
    std::vector<std::string> executable_names = stringOrArrayOfStrings(*my_testcase,"executable_name");

    // SECOND loop over all of the autocheck validations
    for (int which_autocheck = 0; which_autocheck < validators->size(); which_autocheck++) {
      nlohmann::json& autocheck = (*validators)[which_autocheck];
      std::string method = autocheck.value("method","");

      // IF the autocheck has a file to compare (and it's not an executable)...
      if (autocheck.find("actual_file") == autocheck.end()) continue;

      std::vector<std::string> actual_filenames = stringOrArrayOfStrings(autocheck,"actual_file");
      for (int i = 0; i < actual_filenames.size(); i++) {
        std::string actual_file = actual_filenames[i];

        // skip the executables
        bool skip = false;
        for (int j = 0; j < executable_names.size(); j++) {
          if (executable_names[j] == actual_file) { skip = true; continue; }
        }
        if (skip) { continue; }

        // THEN add each actual file to the list of files to archive
        std::stringstream ss;
        ss << "test" << std::setfill('0') << std::setw(2) << which_testcase+1 << "/" << actual_file;
        actual_file = ss.str();
        whole_config["autograding"]["work_to_details"].push_back(actual_file);
      }
    }
  }
}

void AddDockerConfiguration(nlohmann::json &testcases, nlohmann::json &whole_config) {

  int testcase_num = 0;
  for (typename nlohmann::json::iterator itr = testcases.begin(); itr != testcases.end(); itr++,testcase_num++) {
    std::string title = (*itr).value("title","BAD_TITLE");

    if (!(*itr)["use_router"].is_boolean()){
      (*itr)["use_router"] = whole_config["container_options"]["use_router"];
    }

    if (!(*itr)["single_port_per_container"].is_boolean()){
      (*itr)["single_port_per_container"] = whole_config["container_options"]["single_port_per_container"];
    }

    assert(!((*itr)["single_port_per_container"] && (*itr)["use_router"]));


    // if "command" or "solution_commands" exists in whole_config, we must wrap it in a container.
    for(std::pair<std::string, std::string> option : std::vector<std::pair<std::string, std::string>>{
                                                       std::pair<std::string, std::string>{"command", "containers"},
                                                       std::pair<std::string, std::string>{"solution_commands", "solution_containers"}
                                                     }){
      nlohmann::json commands = nlohmann::json::array();
      std::string command_type = option.first;
      std::string container_type = option.second;
      bool found_commands = false;
      if((*itr).find(command_type) != (*itr).end()){
        found_commands = true;
        if ((*itr)[command_type].is_array()){
          commands = (*itr)[command_type];
        }
        else{
          commands.push_back((*itr)[command_type]);
        }

        (*itr).erase(command_type);
      }

      assert ((*itr)[container_type].is_null() || !found_commands);

      if(!(*itr)[container_type].is_null()){
        assert((*itr)[container_type].is_array());
      }else{
        (*itr)[container_type] = nlohmann::json::array();
        //commands may have to be a json::array();
        (*itr)[container_type][0] = nlohmann::json::object();
        (*itr)[container_type][0]["commands"] = commands;
      }

      //get the testcase type
      std::string testcase_type = (*itr).value("type","Execution");
      if (testcase_type == "Compilation"){
        assert((*itr)[container_type].size() == 1);
      }

      if((*itr)[container_type].size() > 1){
        assert(whole_config["autograding_method"] == "docker");
      }

      int router_container = -1;
      bool found_non_server = false;

      for (int container_num = 0; container_num < (*itr)[container_type].size(); container_num++){
        if((*itr)[container_type][container_num]["commands"].is_string()){
          std::string this_command = (*itr)[container_type][container_num].value("commands", "");
          (*itr)[container_type][container_num].erase("commands");
          (*itr)[container_type][container_num]["commands"] = nlohmann::json::array();
          (*itr)[container_type][container_num]["commands"].push_back(this_command);
        }

        if(!(*itr)[container_type][container_num]["commands"].is_array()){
          (*itr)[container_type][container_num]["commands"] = nlohmann::json::array();
        }

        if((*itr)[container_type][container_num]["container_name"].is_null()){
          (*itr)[container_type][container_num]["container_name"] = "container" + std::to_string(container_num);
        }

        if (!(*itr)[container_type][container_num]["number_of_ports"].is_number_integer()){
          (*itr)[container_type][container_num]["number_of_ports"] = whole_config["container_options"]["number_of_ports"];
        }

        if ((*itr)[container_type][container_num]["container_name"] == "router"){
          assert(router_container == -1);
          router_container = container_num;
        }

        if(!(*itr)[container_type][container_num]["server"].is_boolean()){
          (*itr)[container_type][container_num]["server"] = false;
        }

        if((*itr)[container_type][container_num]["server"] == true){
          assert((*itr)[container_type][container_num]["commands"].size() == 0);
        }else{
          found_non_server = true;
        }

        std::string container_name = (*itr)[container_type][container_num]["container_name"];

        assert(std::find_if(container_name.begin(), container_name.end(), isspace) == container_name.end());

        if((*itr)[container_type][container_num]["outgoing_connections"].is_null()){
          (*itr)[container_type][container_num]["outgoing_connections"] = nlohmann::json::array();
        }

        if((*itr)[container_type][container_num]["container_image"].is_null()){
          //TODO: store the default system image somewhere and fill it in here.
          (*itr)[container_type][container_num]["container_image"] = whole_config["container_options"]["container_image"];
        }
      }

      if((*itr)[container_type].size() <= 2 && (*itr)["use_router"]){
        // If there are only two containers specified, make sure that neither is the router.
        // The router MUST go between at least two containers.
        assert(router_container == -1);
      }

      // If we are using the router, and it isn't specified, and this testcase is a valid candidate for a router (2 or more containers specified).
      if((*itr)["use_router"] && router_container == -1 && (*itr)[container_type].size() > 1){
        nlohmann::json insert_router = nlohmann::json::object();
        insert_router["outgoing_connections"] = nlohmann::json::array();
        insert_router["commands"] = nlohmann::json::array();
        insert_router["commands"].push_back("python3 -u submitty_router.py");
        insert_router["container_name"] = "router";
        insert_router["import_default_router"] = true;
        insert_router["container_image"] = "submitty/autograding-default:latest";
        insert_router["server"] = false;
        (*itr)[container_type].push_back(insert_router);
      }
      //We now always add the default router in case of instructor overriding
      else if((*itr)["use_router"] && router_container != -1){
        (*itr)[container_type][router_container]["import_default_router"] = true;
      }
      assert(found_non_server == true);
    }
    assert(!(*itr)["title"].is_null());
    assert(!(*itr)["containers"].is_null());
  }
  return;
}

void FormatDispatcherActions(nlohmann::json &testcases, const nlohmann::json &whole_config) {

  bool docker_enabled = (whole_config["autograding_method"] == "docker");

  int testcase_num = 0;
  for (typename nlohmann::json::iterator itr = testcases.begin(); itr != testcases.end(); itr++,testcase_num++){

    if((*itr)["dispatcher_actions"].is_null()){
      (*itr)["dispatcher_actions"] = nlohmann::json::array();
      continue;
    }

    std::vector<nlohmann::json> dispatcher_actions = mapOrArrayOfMaps((*itr), "dispatcher_actions");

    if(dispatcher_actions.size() > 0){
      assert(docker_enabled);
    }

    for (int i = 0; i < dispatcher_actions.size(); i++){
      nlohmann::json dispatcher_action = dispatcher_actions[i];

      std::string action = dispatcher_action.value("action","");
      assert(action != "");

      if(action == "delay"){
        assert(!dispatcher_action["seconds"].is_null());
        assert(!dispatcher_action["delay"].is_string());

        float delay_time_in_seconds = 1.0;
        delay_time_in_seconds = float(dispatcher_action.value("seconds",1.0));
        dispatcher_action["seconds"] = delay_time_in_seconds;
      }else{

        assert(!dispatcher_action["containers"].is_null());
        nlohmann::json containers = nlohmann::json::array();
        if (dispatcher_action["containers"].is_array()){
          containers = dispatcher_action["containers"];
        }
        else{
          containers.push_back(dispatcher_action["containers"]);
        }

        dispatcher_action.erase("containers");
        dispatcher_action["containers"] = containers;

        if(action == "stdin"){
          assert(!dispatcher_action["string"].is_null());
          (*itr)["dispatcher_actions"][i] = dispatcher_action;
        }else{
          assert(action == "stop" || action == "start" || action == "kill");
        }
      }
    }
  }
}


/*
* Given an action, makes sure that the mouse button in the action is valid (left, right, middle).
* If there is no mouse button specified, "left" is added.
*/
void validate_mouse_button(nlohmann::json& action){
  if(action["mouse_button"].is_null()){
    action["mouse_button"] = "left";
  }else{
    assert(action["mouse_button"].is_string());
    assert(action["mouse_button"] == "left" || action["mouse_button"] == "middle" || action["mouse_button"] == "right");
  }
}

/*
* Given an action and a field, makes certain that the value in the field is an integer greater than min_val.
* if the field doesn't exist and populate_default is true, the field is set to default_value.
*/
void validate_integer(nlohmann::json& action, std::string field, bool populate_default, int min_val, int default_value){
  if(action.find(field) != action.end()){
    std::string action_name = action["action"];

    if(!action[field].is_number_integer()){
      std::cout << "ERROR: For the " << action_name << " action, " << field << " must be an integer." << std::endl;
    }
    assert(action[field].is_number_integer());

    if(action[field] < min_val){
      std::cout << "ERROR: For the " << action_name << " action, " << field << " must be greater than " << min_val << "." << std::endl;
    }
    assert(action[field] >= min_val);
  } else{
    if(populate_default){
      action[field] = default_value;
    }
  }
}

void validate_gif_or_screenshot_name(std::string filename){

  if(filename.find(".") != std::string::npos){
    std::cout << "ERROR: screenshot and gif names should not contain file extensions. File extensions will be added automatically." << std::endl;
  }
  if(filename.find(" ") != std::string::npos){
    std::cout << "ERROR: screenshot and gif names should not contain spaces." << std::endl;
  }
  if(filename.find("/") != std::string::npos||
     filename.find("$") != std::string::npos||
     filename.find("'") != std::string::npos||
     filename.find("\"") != std::string::npos||
     filename.find("\\") != std::string::npos){
    std::cout << "ERROR: screenshot and gif names should not contain special characters." << std::endl;
  }
  assert(filename.find(" ") == std::string::npos);
  assert(filename.find(".") == std::string::npos);
  assert(filename.find("/") == std::string::npos);
  assert(filename.find("$") == std::string::npos);
  assert(filename.find("'") == std::string::npos);
  assert(filename.find("\"") == std::string::npos);
  assert(filename.find("\\") == std::string::npos);
  assert(filename.find("*") == std::string::npos);
}

void FormatGraphicsActions(nlohmann::json &testcases, nlohmann::json &whole_config) {

  int testcase_num = 0;
  for (typename nlohmann::json::iterator itr = testcases.begin(); itr != testcases.end(); itr++,testcase_num++){
    //screenshot number resets per testcase.
    int number_of_screenshots = 0;
    int number_of_gifs = 0;

    if((*itr).find("actions") == (*itr).end()){
      continue;
    }

    std::vector<nlohmann::json> actions = mapOrArrayOfMaps((*itr), "actions");

    for (int action_num = 0; action_num < actions.size(); action_num++){
      nlohmann::json action = actions[action_num];

      std::string action_name = action.value("action","");
      assert(action != "");

      //Origin and center have no additional fields.
      if(action_name == "origin" || action_name == "center"){
        continue;
      }
      // Delay requires a float number of seconds, which must be greater than 0
      // and defaults to 1.
      else if(action_name == "delay"){

        if(action["seconds"].is_null()){
          std::cout << "ERROR: all delay actions must have a number of seconds specified." << std::endl;
        }
        assert(!action["seconds"].is_null());

        if(!action["seconds"].is_number()){
          std::cout << "ERROR: all delay actions must have a number of seconds specified." << std::endl;
        }
        assert(action["seconds"].is_number());

        float delay_time_in_seconds = float(action.value("seconds",1.0));

        assert(delay_time_in_seconds > 0);
        action["seconds"] = delay_time_in_seconds;
      }
      //screenshot can contain an optional name. Otherwise, it is labeled in numerical order.
      else if(action_name == "screenshot"){
        if(action["name"].is_null()){
          action["name"] = "screenshot_" + std::to_string(number_of_screenshots) + ".png";
        }else{
          if(!action["name"].is_string()){
            std::cout << "ERROR: if a screenshot name is specified, it must be a string." << std::endl;
          }
          assert(action["name"].is_string());
          assert(action["name"] != "");
          std::string screenshot_name = action["name"];
          validate_gif_or_screenshot_name(screenshot_name);
          action["name"] = screenshot_name + ".png";
        }
        number_of_screenshots++;
      }
      //Type requires a string to type and can have an optional "delay_in_seconds" and "presses"
      else if(action_name == "type"){
        float delay_time_in_seconds = action.value("delay_in_seconds",0.1);
        if(delay_time_in_seconds <= 0){
          std::cout << "ERROR: In the type command, delay must be greater than zero." << std::endl;
        }
        assert(delay_time_in_seconds > 0);
        action["delay_in_seconds"] = delay_time_in_seconds;

        validate_integer(action, "presses", true, 1, 1);

        if(action["string"].is_null()){
          std::cout << "ERROR: an output string must be specified in the type command." << std::endl;
        }
        assert(!action["string"].is_null());
        assert( action["string"].is_string());
        assert( action["string"] != "");

      }
      //Type requires a key_combination to press and can have an optional "delay_in_seconds" and "presses"
      else if(action_name == "key"){
        float delay_time_in_seconds = action.value("delay_in_seconds",0.1);

        if(delay_time_in_seconds <= 0){
          std::cout << "ERROR: In the key command, delay must be greater than zero." << std::endl;
        }
        assert(delay_time_in_seconds > 0);
        action["delay_in_seconds"] = delay_time_in_seconds;

        validate_integer(action, "presses", true, 1, 1);

        if(action["key_combination"].is_null()){
          std::cout << "ERROR: key combination to be pressed must be specified in the key command." << std::endl;
        }
        assert(!action["key_combination"].is_null());
        assert( action["key_combination"].is_string());
        assert( action["key_combination"] != "");
      }
      //Click and drag can have an optional start_x and start_y, and must have an end_x and end_y
      // which are greater than 0.
      else if(action_name == "click and drag"){

        validate_mouse_button(action);

        validate_integer(action, "start_x", false, 0, 0);
        validate_integer(action, "start_y", false, 0, 0);
        validate_integer(action, "end_x",   true,  0, 0);
        validate_integer(action, "end_y",   true,  0, 0);

        if(action["end_x"] == 0 && action["end_y"] == 0){
          std::cout << "ERROR: some movement must be specified in click and drag" << std::endl;
        }
        assert(action["end_x"] != 0 || action["end_y"] != 0);

      }
      //Click and drag delta can have an optional mouse button, and must have and end_x and end_y.
      else if(action_name == "click and drag delta"){

        validate_mouse_button(action);

        validate_integer(action, "end_x",   true,  -100000, 0);
        validate_integer(action, "end_y",   true,  -100000, 0);

        if(action["end_x"] == 0 && action["end_y"] == 0){
          std::cout << "ERROR: some movement must be specified in click and drag" << std::endl;
        }

        assert(action["end_x"] != 0 || action["end_y"] != 0);

      }
      //Click has an optional mouse button.
      else if(action_name == "click"){
        validate_mouse_button(action);
      }
      //Mouse move has an optional end_x and end_y.
      else if(action_name == "mouse move" || action_name == "move mouse" || action_name == "move"){
        validate_integer(action, "end_x", true, 0, 0);
        validate_integer(action, "end_y", true, 0, 0);
        action["action"] = "move mouse";
      }
      //gif requires a duration and can optionally have a name.
      else if(action_name == "gif"){

        if(action["seconds"].is_null()){
          std::cout << "ERROR: all delay actions must have a number of seconds specified." << std::endl;
        }
        assert(!action["seconds"].is_null());

        if(!action["seconds"].is_number()){
          std::cout << "ERROR: all delay actions must have a number of seconds specified." << std::endl;
        }
        assert(action["seconds"].is_number());

        float gif_duration = float(action.value("seconds",1.0));
        assert(gif_duration > 0);
        action["seconds"] = gif_duration;

        if(action["name"].is_null()){
          action["name"] = "gif_" + std::to_string(number_of_gifs);
        }
        else{
          if(!action["name"].is_string()){
            std::cout << "ERROR: if a screenshot name is specified, it must be a string." << std::endl;
          }
          assert(action["name"].is_string());
          assert(action["name"] != "");
          std::string gif_name = action["name"];
          validate_gif_or_screenshot_name(gif_name);
        }

        if(action["preserve_individual_frames"].is_null()){
          action["preserve_individual_frames"] = false;
        }else{
          assert(action["preserve_individual_frames"].is_boolean());
        }
        //minimum frames_per_second is 1, default is 10.
        validate_integer(action, "frames_per_second", true, 1, 10);

        if(action["frames_per_second"] > 30){
          std::cout << "ERROR: Submitty does not allow gifs with an fps greater than 30." << std::endl;
          assert(action["frames_per_second"] <= 30);
        }

        number_of_gifs++;
      }

      //Fail if the action is not valid.
      else{
        bool valid_action_type = false;
        if(action_name == ""){
          std::cout << "ERROR: no 'action' field defined." << std::endl;
        }else{
          std::cout << "ERROR: Could not recognize action " << action_name << std::endl;
        }
        assert(valid_action_type == true);
      }
      (*itr)["actions"][action_num] = action;
    }
  }
}

void formatPreActions(nlohmann::json &testcases, nlohmann::json &whole_config) {
  // loop over testcases
  int which_testcase = 0;
  for (nlohmann::json::iterator my_testcase = testcases.begin(); my_testcase != testcases.end(); my_testcase++, which_testcase++) {

    if((*my_testcase)["pre_commands"].is_null()){
      (*my_testcase)["pre_commands"] = nlohmann::json::array();
      continue;
    }

    std::vector<nlohmann::json> pre_commands = mapOrArrayOfMaps((*my_testcase), "pre_commands");

    for (int i = 0; i < pre_commands.size(); i++){
      nlohmann::json pre_command = pre_commands[i];

      //right now we only support copy.
      assert(pre_command["command"] == "cp");

      if(!pre_command["option"].is_string()){
        pre_command["option"] = "-R";
      }

      //right now we only support recursive.
      assert(pre_command["option"] == "-R");

      assert(pre_command["testcase"].is_string());

      std::string testcase = pre_command["testcase"];

      //remove trailing slash
      if(testcase.length() == 7){
        testcase = testcase.substr(0,6);
      }

      assert(testcase.length() == 6);

      std::string prefix = testcase.substr(0,4);
      assert(prefix == "test");

      std::string number = testcase.substr(4,6);
      int remainder = std::stoi( number );

      //we must be referencing a previous testcase. (+1 because we start at 0)
      assert(remainder > 0);
      assert(remainder < which_testcase+1);

      pre_command["testcase"] = testcase;


      //The source must be a string.
      assert(pre_command["source"].is_string());

      //the source must be of the form prefix = test, remainder is less than size 3 and is an int.
      std::string source_name = pre_command["source"];

      assert(source_name[0] != '/');

      //The command must not container .. or $.
      assert(source_name.find("..") == std::string::npos);
      assert(source_name.find("$")  == std::string::npos);
      assert(source_name.find("~")  == std::string::npos);
      assert(source_name.find("\"") == std::string::npos);
      assert(source_name.find("\'") == std::string::npos);

      if(!pre_command["pattern"].is_string()){
         (*my_testcase)["pattern"] = "";
      }else{
        std::string pattern = pre_command["pattern"];
        //The pattern must not container .. or $
        assert(pattern.find("..") == std::string::npos);
        assert(pattern.find("$") == std::string::npos);
        assert(pattern.find("~") == std::string::npos);
        assert(pattern.find("\"") == std::string::npos);
        assert(pattern.find("\'") == std::string::npos);
      }

      //there must be a destination
      assert(pre_command["destination"].is_string());

      std::string destination = pre_command["destination"];

      //The destination must not container .. or $
      assert(destination.find("..") == std::string::npos);
      assert(destination.find("$") == std::string::npos);
      assert(destination.find("~") == std::string::npos);
      assert(destination.find("\"") == std::string::npos);
      assert(destination.find("\'") == std::string::npos);

      (*my_testcase)["pre_commands"][i] = pre_command;
    }
  }
}

void RewriteDeprecatedMyersDiff(nlohmann::json &testcases, nlohmann::json &whole_config) {
  // loop over testcases
  int which_testcase = 0;
  for (nlohmann::json::iterator my_testcase = testcases.begin(); my_testcase != testcases.end(); my_testcase++,which_testcase++) {
    nlohmann::json::iterator validators = my_testcase->find("validation");
    if (validators == my_testcase->end()) {
      /* no autochecks */
      continue;
    }
    // loop over autochecks
    for (int which_autocheck = 0; which_autocheck < validators->size(); which_autocheck++) {
      nlohmann::json& autocheck = (*validators)[which_autocheck];
      std::string method = autocheck.value("method","");
      std::string comparison = autocheck.value("comparison","");

      // if autocheck if old byLinebyWord format... make it byLinebyChar
      if (comparison == "byLinebyWord") {
          autocheck["comparison"] = "byLinebyChar";
      }

      // if autocheck is old myersdiff format...  rewrite it!
      if (method == "myersDiffbyLinebyChar") {
        autocheck["method"] = "diff";
        assert (autocheck.find("comparison") == autocheck.end());
        autocheck["comparison"] = "byLinebyChar";
      } else if (method == "myersDiffbyLinebyWord") {
        autocheck["method"] = "diff";
        assert (autocheck.find("comparison") == autocheck.end());
        autocheck["comparison"] = "byLinebyChar";
      } else if (method == "myersDiffbyLine") {
        autocheck["method"] = "diff";
        assert (autocheck.find("comparison") == autocheck.end());
        autocheck["comparison"] = "byLine";
      } else if (method == "myersDiffbyLineNoWhite") {
        autocheck["method"] = "diff";
        assert (autocheck.find("comparison") == autocheck.end());
        autocheck["comparison"] = "byLine";
        assert (autocheck.find("ignoreWhitespace") == autocheck.end());
        autocheck["ignoreWhitespace"] = true;
      } else if (method == "diffLineSwapOk") {
        autocheck["method"] = "diff";
        assert (autocheck.find("comparison") == autocheck.end());
        autocheck["comparison"] = "byLine";
        assert (autocheck.find("lineSwapOk") == autocheck.end());
        autocheck["lineSwapOk"] = true;
      }
    }
  }
}

void InflateTestcases(nlohmann::json &testcases, nlohmann::json &whole_config){

  for (typename nlohmann::json::iterator itr = testcases.begin(); itr != testcases.end(); itr++){
      InflateTestcase(*itr, whole_config);
  }
}

bool validShowValue(const nlohmann::json& v) {
  return (v.is_string() &&
          (v == "always" ||
           v == "never" ||
           v == "on_failure" ||
           v == "on_success"));
}

void InflateTestcase(nlohmann::json &single_testcase, nlohmann::json &whole_config) {
  //move to load_json
  General_Helper(single_testcase);

  if (!single_testcase["timestamped_stdout"].is_boolean()){
    single_testcase["timestamped_stdout"] = whole_config["timestamped_stdout"];
  }

  if (!single_testcase["publish_actions"].is_boolean()) {
    single_testcase["publish_actions"] = whole_config["publish_actions"];
  }

  if (single_testcase.value("type","Execution") == "FileCheck") {
    FileCheck_Helper(single_testcase);
  } else if (single_testcase.value("type","Execution") == "Compilation") {
    Compilation_Helper(single_testcase);
  } else {
    assert (single_testcase.value("type","Execution") == "Execution");
    Execution_Helper(single_testcase);
  }

  nlohmann::json::iterator itr = single_testcase.find("validation");
  if (itr != single_testcase.end()) {
    assert (itr->is_array());
    VerifyGraderDeductions(*itr);
    std::vector<nlohmann::json> containers = mapOrArrayOfMaps(single_testcase, "containers");
    AddDefaultGraphicsChecks(*itr,single_testcase);
    AddDefaultGraders(containers,*itr,whole_config);
     for (int i = 0; i < (*itr).size(); i++) {
      nlohmann::json& grader = (*itr)[i];
      nlohmann::json::iterator itr2;
      std::string method = grader.value("method","MISSING METHOD");
      itr2 = grader.find("show_message");
      if (itr2 == grader.end()) {
        if (method == "warnIfNotEmpty" || method == "warnIfEmpty") {
          grader["show_message"] = "on_failure";
        } else {
          if (grader.find("actual_file") != grader.end() &&
              *(grader.find("actual_file")) == "execute_logfile.txt" &&
              grader.find("show_actual") != grader.end() &&
              *(grader.find("show_actual")) == "never") {
            grader["show_message"] = "never";
          } else {
            grader["show_message"] = "always";
          }
        }
      } else {
        assert (validShowValue(*itr2));
      }
      if (grader.find("actual_file") != grader.end()) {
        itr2 = grader.find("show_actual");
        if (itr2 == grader.end()) {
          if (method == "warnIfNotEmpty" || method == "warnIfEmpty") {
            grader["show_actual"] = "on_failure";
          } else {
            grader["show_actual"] = "always";
          }
        } else {
          assert (validShowValue(*itr2));
        }
      }
      if (grader.find("expected_file") != grader.end()) {
        itr2 = grader.find("show_expected");
        if (itr2 == grader.end()) {
          grader["show_expected"] = "always";
        } else {
          assert (validShowValue(*itr2));
        }
      }
    }
  }
}

// =====================================================================
// =====================================================================

nlohmann::json LoadAndProcessConfigJSON(const std::string &rcsid) {
  nlohmann::json answer;
  std::stringstream sstr(GLOBAL_config_json_string);
  sstr >> answer;

  AddGlobalDefaults(answer);

  /**
  * Validate/complete-ify the global testcases array.
  **/
  nlohmann::json::iterator testcases = answer.find("testcases");
  if(testcases == answer.end()) {
    answer["testcases"] = nlohmann::json::array();
    testcases = answer.find("testcases");
  }

  AddDockerConfiguration(*testcases, answer);
  FormatDispatcherActions(*testcases, answer);
  formatPreActions(*testcases, answer);
  FormatGraphicsActions(*testcases, answer);
  RewriteDeprecatedMyersDiff(*testcases, answer);
  InflateTestcases(*testcases, answer);
  ArchiveValidatedFiles(*testcases, answer);
  PreserveCompiledFiles(*testcases, answer);

  /**
  * Validate/complete-ify the testcases in each item in the item_pool.
  **/
  nlohmann::json::iterator item_pool = answer.find("item_pool");
  if(item_pool != answer.end()) {
    for(nlohmann::json::iterator item = answer["item_pool"].begin(); item != answer["item_pool"].end(); item++) {
      nlohmann::json::iterator item_testcases = (*item).find("testcases");
      if(item_testcases == (*item).end()) {
        (*item)["testcases"] = nlohmann::json::array();
        item_testcases = (*item).find("testcases");
      }
      AddDockerConfiguration(*item_testcases, answer);
      FormatDispatcherActions(*item_testcases, answer);
      formatPreActions(*item_testcases, answer);
      FormatGraphicsActions(*item_testcases, answer);
      RewriteDeprecatedMyersDiff(*item_testcases, answer);
      InflateTestcases(*item_testcases, answer);
      ArchiveValidatedFiles(*item_testcases, answer);
      PreserveCompiledFiles(*item_testcases, answer);
    }
  }


  AddSubmissionLimitTestCase(answer);
  if (rcsid != "") {
    CustomizeAutoGrading(rcsid,answer);
  }
  return answer;
}

/**
* Add automatic GIF filechecks.
*/
void AddDefaultGraphicsChecks(nlohmann::json &json_graders, const nlohmann::json &testcase){
  if(testcase.find("actions") == testcase.end()){
    return;
  }

  std::vector<nlohmann::json> actions = mapOrArrayOfMaps(testcase, "actions");

  for (int action_num = 0; action_num < actions.size(); action_num++){
    nlohmann::json action = actions[action_num];
    std::string action_name = action.value("action","");
    if(action_name != "gif"){
      continue;
    }
    /*
    //We found a gif! Add a filecheck for it.
    // let's not add it automatically right now, more useful to leave it to configuration to specify the description etc.
    nlohmann::json j;
    std::string gif_name = action["name"];
    std::string full_gif_name = gif_name + ".gif";

    j["actual_file"] = full_gif_name;
    j["deduction"]   = 0;
    j["description"] = "Student GIF: " + gif_name;
    j["method"]      = "warnIfEmpty";
    j["show_actual"] = "always";
    j["show_message"]= "never";
    json_graders.push_back(j);
    */
  }
}

/*
* Start
*/

// Every command sends standard output and standard error to two
// files.  Make sure those files are sent to a grader.
void AddDefaultGraders(const std::vector<nlohmann::json> &containers,
                       nlohmann::json &json_graders,
                       const nlohmann::json &whole_config) {
  std::set<std::string> files_covered;
  assert (json_graders.is_array());

  //Find the names of every file explicitly checked for by the instructor (we don't have to add defaults for these.)
  for (int i = 0; i < json_graders.size(); i++) {
    std::vector<std::string> filenames = stringOrArrayOfStrings(json_graders[i],"actual_file");
    for (int j = 0; j < filenames.size(); j++) {
      files_covered.insert(filenames[j]);
    }
  }

  //For every container, for every command, we want to add default graders for the appropriate files.
  for (int i = 0; i < containers.size(); i++) {

    if(containers[i]["server"] == true){
      continue;
    }

    std::string prefix = "";
    //If there are more than one containers, we do need to prepend its directory (e.g. container1/).
    if(containers.size() > 1){
      std::string container_name = containers[i].value("container_name","");
      assert(container_name != "");
      prefix = container_name + "/";
    }
    std::vector<std::string> commands = stringOrArrayOfStrings(containers[i],"commands");
    for(int j = 0; j < commands.size(); j++){
      std::string suffix = ".txt";

      //If this container contains multiple commands, we need to append a number to its STDOUT/ERR
      if (commands.size() > 1){
        suffix = "_"+std::to_string(j)+".txt";
      }

      AddDefaultGrader(containers[i]["commands"][j],files_covered,json_graders,prefix+"STDOUT"+suffix,whole_config);
      AddDefaultGrader(containers[i]["commands"][j],files_covered,json_graders,prefix+"STDERR"+suffix,whole_config);
    }
  }
}

// If we don't already have a grader for the indicated file, add a
// simple "WarnIfNotEmpty" check, that will print the contents of the
// file to help the student debug if their output has gone to the
// wrong place or if there was an execution error
void AddDefaultGrader(const std::string &command,
                      const std::set<std::string> &files_covered,
                      nlohmann::json& json_graders,
                      const std::string &filename,
                      const nlohmann::json &whole_config) {
  if (files_covered.find(filename) != files_covered.end())
    return;
  //std::cout << "ADD GRADER WarnIfNotEmpty test for " << filename << std::endl;
  nlohmann::json j;
  j["method"] = "warnIfNotEmpty";
  j["actual_file"] = filename;
  if (filename.find("STDOUT") != std::string::npos) {
    j["description"] = "Standard Output ("+filename+")";
  } else if (filename.find("STDERR") != std::string::npos) {
    std::string program_name = get_program_name(command,whole_config);
    if (program_name == "/usr/bin/python") {
      j["description"] = "syntax error output from running python";
    } else if (program_name.find("java") != std::string::npos) {
      j["description"] = "syntax error output from running java";
      if (program_name.find("javac") != std::string::npos) {
        j["description"] = "syntax error output from compiling java";
      }
      if (j["method"] == "warnIfNotEmpty" || j["method"] == "errorIfNotEmpty") {
        j["jvm_memory"] = true;
      }
    } else {
      j["description"] = "Standard Error ("+filename+")";
    }
  } else {
    j["description"] = "DEFAULTING TO "+filename;
  }
  j["deduction"] = 0.0;
  j["show_message"] = "on_failure";
  j["show_actual"] = "on_failure";
  json_graders.push_back(j);
}

void General_Helper(nlohmann::json &single_testcase) {
  nlohmann::json::iterator itr;

  // Check the required fields for all test types
  itr = single_testcase.find("title");
  assert (itr != single_testcase.end() && itr->is_string());

  // Check the type of the optional fields
  itr = single_testcase.find("description");
  if (itr != single_testcase.end()) { assert (itr->is_string()); }
  itr = single_testcase.find("points");
  if (itr != single_testcase.end()) { assert (itr->is_number()); }
}

void FileCheck_Helper(nlohmann::json &single_testcase) {
  nlohmann::json::iterator f_itr,v_itr,m_itr,itr;

  // Check the required fields for all test types
  f_itr = single_testcase.find("actual_file");
  v_itr = single_testcase.find("validation");
  m_itr = single_testcase.find("max_submissions");

  if (f_itr != single_testcase.end()) {
    // need to rewrite to use a validation
    assert (m_itr == single_testcase.end());
    assert (v_itr == single_testcase.end());
    nlohmann::json v;
    v["method"] = "fileExists";
    v["actual_file"] = (*f_itr);
    std::vector<std::string> filenames = stringOrArrayOfStrings(single_testcase,"actual_file");
    std::string desc;
    for (int i = 0; i < filenames.size(); i++) {
      if (i != 0) desc += " ";
      desc += filenames[i];
    }
    v["description"] = desc;
    if (filenames.size() != 1) {
      v["show_actual"] = "never";
    }
    if (single_testcase.value("one_of",false)) {
      v["one_of"] = true;
    }
    single_testcase["validation"].push_back(v);
    single_testcase.erase(f_itr);
  } else if (v_itr != single_testcase.end()) {
    // already has a validation
  } else {
    assert (m_itr != single_testcase.end());
    assert (m_itr->is_number_integer());
    assert ((int)(*m_itr) >= 1);

    itr = single_testcase.find("points");
    if (itr == single_testcase.end()) {
      single_testcase["points"] = -5;
    } else {
      assert (itr->is_number_integer());
      assert ((int)(*itr) <= 0);
    }
    itr = single_testcase.find("penalty");
    if (itr == single_testcase.end()) {
      single_testcase["penalty"] = -0.1;
    } else {
      assert (itr->is_number());
      assert ((*itr) <= 0);
    }
    itr = single_testcase.find("title");
    if (itr == single_testcase.end()) {
      single_testcase["title"] = "Submission Limit";
    } else {
      assert (itr->is_string());
    }
  }
}

bool HasActualFileCheck(const nlohmann::json &v_itr, const std::string &actual_file) {
  assert (actual_file != "");
  const std::vector<nlohmann::json> tmp = v_itr.get<std::vector<nlohmann::json> >();
  for (int i = 0; i < tmp.size(); i++) {
    if (tmp[i].value("actual_file","") == actual_file) {
      return true;
    }
  }
  return false;
}

void Compilation_Helper(nlohmann::json &single_testcase) {
  nlohmann::json::iterator f_itr,v_itr,w_itr;

  // Check the required fields for all test types
  f_itr = single_testcase.find("executable_name");
  v_itr = single_testcase.find("validation");

  if (v_itr != single_testcase.end()) {
    assert (v_itr->is_array());
    std::vector<nlohmann::json> tmp = v_itr->get<std::vector<nlohmann::json> >();
  }

  if (f_itr != single_testcase.end()) {
    //assert that there is exactly 1 container
    assert(!single_testcase["containers"].is_null());
    //assert that the container has commands
    assert(!single_testcase["containers"][0]["commands"].is_null());
    nlohmann::json commands = single_testcase["containers"][0]["commands"];
    //grab the container's commands.
    assert (commands.size() > 0);
    for (int i = 0; i < commands.size(); i++) {
      w_itr = single_testcase.find("warning_deduction");
      float warning_fraction = 0.0;
      if (w_itr != single_testcase.end()) {
        assert (w_itr->is_number());
        warning_fraction = (*w_itr);
        single_testcase.erase(w_itr);
      }
      assert (warning_fraction >= 0.0 && warning_fraction <= 1.0);
      nlohmann::json v2;
      v2["method"] = "errorIfNotEmpty";
      if (commands.size() == 1) {
        v2["actual_file"] = "STDERR.txt";
      } else {
        v2["actual_file"] = "STDERR_" + std::to_string(i) + ".txt";
      }
      v2["description"] = "Compilation Errors and/or Warnings";
      nlohmann::json command_json = commands[i];
      assert (command_json.is_string());
      std::string command = command_json;
      if (command.find("java") != std::string::npos) {
        v2["jvm_memory"] = true;
      }
      v2["show_actual"] = "on_failure";
      v2["show_message"] = "on_failure";
      v2["deduction"] = warning_fraction;

      v_itr = single_testcase.find("validation");
      if (v_itr == single_testcase.end() ||
          !HasActualFileCheck(*v_itr,v2["actual_file"])) {
        single_testcase["validation"].push_back(v2);
      }
    }


    std::vector<std::string> executable_names = stringOrArrayOfStrings(single_testcase,"executable_name");
    assert (executable_names.size() > 0);
    for (int i = 0; i < executable_names.size(); i++) {
      nlohmann::json v;
      v["method"] = "fileExists";
      v["actual_file"] = executable_names[i];
      v["description"] = "Create Executable";
      v["show_actual"] = "on_failure";
      v["show_message"] = "on_failure";
      v["deduction"] = 1.0/executable_names.size();

      v_itr = single_testcase.find("validation");
      if (v_itr == single_testcase.end() ||
          !HasActualFileCheck(*v_itr,v["actual_file"])) {
        single_testcase["validation"].push_back(v);
      }
    }
  }

  v_itr = single_testcase.find("validation");

  if (v_itr != single_testcase.end()) {
    assert (v_itr->is_array());
    std::vector<nlohmann::json> tmp = v_itr->get<std::vector<nlohmann::json> >();
  }

  assert (v_itr != single_testcase.end());
}

void Execution_Helper(nlohmann::json &single_testcase) {
  nlohmann::json::iterator itr = single_testcase.find("validation");
  assert (itr != single_testcase.end());
  for (nlohmann::json::iterator itr2 = (itr)->begin(); itr2 != (itr)->end(); itr2++) {
    nlohmann::json& j = *itr2;
    std::string method = j.value("method","");
    std::string description = j.value("description","");
    if (description=="") {
      if (method == "EmmaInstrumentationGrader") {
        j["description"] = "EMMA instrumentation output";
      } else if (method =="JUnitTestGrader") {
        j["description"] = "JUnit output";
      } else if (method =="EmmaCoverageReportGrader") {
        j["description"] = "EMMA coverage report";
      } else if (method =="JaCoCoCoverageReportGrader") {
        j["description"] = "JaCoCo coverage report";
      } else if (method =="MultipleJUnitTestGrader") {
        j["description"] = "TestRunner output";
      }
    }
  }

  //assert (commands.size() > 0);

}

// Go through the instructor-written test cases.
//   If the autograding points are non zero, and
//   if the instructor didn't add a penalty for excessive submissions, then
//   add a standard small penalty for > 20 submissions.
//
void AddSubmissionLimitTestCase(nlohmann::json &config_json) {
  int total_points = 0;
  bool has_limit_test = false;

  // count total points and search for submission limit testcase
  nlohmann::json::iterator tc = config_json.find("testcases");
  assert (tc != config_json.end());
  for (unsigned int i = 0; i < tc->size(); i++) {
    //This input to testcase is only necessary if the testcase needs to retrieve its 'commands'
    std::string container_name = "";
    TestCase my_testcase(config_json,i,container_name);
    int points = (*tc)[i].value("points",0);
    if (points > 0) {
      total_points += points;
    }
    if (my_testcase.isSubmissionLimit()) {
      has_limit_test = true;
    }
  }

  // add submission limit test case
  if (!has_limit_test) {
    nlohmann::json limit_test;
    limit_test["type"] = "FileCheck";
    limit_test["title"] = "Submission Limit";
    limit_test["max_submissions"] = MAX_NUM_SUBMISSIONS;
    if (total_points > 0) {
      limit_test["points"] = -5;
      limit_test["penalty"] = -0.1;
    } else {
      limit_test["points"] = 0;
      limit_test["penalty"] = 0;
    }
    config_json["testcases"].push_back(limit_test);
  }


  // FIXME:  ugly...  need to reset the id...
  //TestCase::reset_next_test_case_id();
}

void CustomizeAutoGrading(const std::string& username, nlohmann::json& j) {
  if (j.find("string_replacement") != j.end()) {
    // Read and check string replacement variables
    nlohmann::json j2 = j["string_replacement"];
    std::string placeholder = j2.value("placeholder","");
    assert (placeholder != "");
    std::string replacement = j2.value("replacement","");
    assert (replacement != "");
    assert (replacement == "hashed_username");
    int mod_value = j2.value("mod",-1);
    assert (mod_value > 0);

    int A = 54059; /* a prime */
    int B = 76963; /* another prime */
    int FIRSTH = 37; /* also prime */
    unsigned int sum = FIRSTH;
    for (int i = 0; i < username.size(); i++) {
      sum = (sum * A) ^ (username[i] * B);
    }
    int assigned = (sum % mod_value)+1;

    std::string repl = std::to_string(assigned);

    nlohmann::json::iterator association = j2.find("association");
    if (association != j2.end()) {
      repl = (*association)[repl];
    }

    nlohmann::json::iterator itr = j.find("testcases");
    if (itr != j.end()) {
      RecursiveReplace(*itr,placeholder,repl);
    }
  }
}

void RecursiveReplace(nlohmann::json& j, const std::string& placeholder, const std::string& replacement) {
  if (j.is_string()) {
    std::string str = j.get<std::string>();
    int pos = str.find(placeholder);
    if (pos != std::string::npos) {
      std::cout << "REPLACING '" << str << "' with '";
      str.replace(pos,placeholder.length(),replacement);
      std::cout << str << "'" << std::endl;
      j = str;
    }
  } else if (j.is_array() || j.is_object()) {
    for (nlohmann::json::iterator itr = j.begin(); itr != j.end(); itr++) {
      RecursiveReplace(*itr,placeholder,replacement);
    }
  }
}

// Make sure the sum of deductions across graders adds to at least 1.0.
// If a grader does not have a deduction setting, set it to 1/# of (non default) graders.
void VerifyGraderDeductions(nlohmann::json &json_graders) {
  assert (json_graders.is_array());
  assert (json_graders.size() > 0);

  int json_grader_count = 0;
  for (int i = 0; i < json_graders.size(); i++) {
    nlohmann::json::const_iterator itr = json_graders[i].find("method");
    if (itr != json_graders[i].end()) {
      json_grader_count++;
    }
  }

  assert (json_grader_count > 0);

  float default_deduction = 1.0 / float(json_grader_count);
  float sum = 0.0;
  for (int i = 0; i < json_graders.size(); i++) {
    nlohmann::json::const_iterator itr = json_graders[i].find("method");
    if (itr == json_graders[i].end()) {
      json_graders[i]["deduction"] = 0;
      continue;
    }
    itr = json_graders[i].find("deduction");
    float deduction;
    if (itr == json_graders[i].end()) {
      json_graders[i]["deduction"] = default_deduction;
      deduction = default_deduction;
    } else {
      assert (itr->is_number());
      deduction = (*itr);
    }
    sum += deduction;
  }

  if (sum < 0.99) {
    std::cout << "ERROR! DEDUCTION SUM < 1.0: " << sum << std::endl;
  }
}
