#include <unistd.h>
#include <cstdlib>
#include <string>
#include <iostream>
#include <cassert>

#include "TestCase.h"
#include "load_config_json.h"
#include "execute.h"

extern const char *GLOBAL_config_json_string;  // defined in json_generated.cpp

void AddAutogradingConfiguration(nlohmann::json &whole_config) {
  whole_config["autograding"]["submission_to_compilation"].push_back("**/*.cpp");
  whole_config["autograding"]["submission_to_compilation"].push_back("**/*.cxx");
  whole_config["autograding"]["submission_to_compilation"].push_back("**/*.c");
  whole_config["autograding"]["submission_to_compilation"].push_back("**/*.h");
  whole_config["autograding"]["submission_to_compilation"].push_back("**/*.hpp");
  whole_config["autograding"]["submission_to_compilation"].push_back("**/*.hxx");
  whole_config["autograding"]["submission_to_compilation"].push_back("**/*.java");

  whole_config["autograding"]["submission_to_runner"].push_back("**/*.py");
  whole_config["autograding"]["submission_to_runner"].push_back("**/*.pdf");

  whole_config["autograding"]["compilation_to_runner"].push_back("**/*.out");
  whole_config["autograding"]["compilation_to_runner"].push_back("**/*.class");

  whole_config["autograding"]["compilation_to_validation"].push_back("test*/STDOUT*.txt");
  whole_config["autograding"]["compilation_to_validation"].push_back("test*/STDERR*.txt");

  whole_config["autograding"]["submission_to_validation"].push_back("**/README.txt");
  whole_config["autograding"]["submission_to_validation"].push_back("textbox_*.txt");
  whole_config["autograding"]["submission_to_validation"].push_back("**/*.pdf");

  whole_config["autograding"]["work_to_details"].push_back("test*/*.txt");
  whole_config["autograding"]["work_to_details"].push_back("test*/*_diff.json");
  whole_config["autograding"]["work_to_details"].push_back("**/README.txt");
  whole_config["autograding"]["work_to_details"].push_back("textbox_*.txt");
  //todo check up on how this works.
  whole_config["autograding"]["work_to_details"].push_back("test*/textbox_*.txt");

  if (whole_config["autograding"].find("use_checkout_subdirectory") == whole_config["autograding"].end()) {
    whole_config["autograding"]["use_checkout_subdirectory"] = "";
  }
}

void AddDockerConfiguration(nlohmann::json &whole_config) {

  if (!whole_config["docker_enabled"].is_boolean()){
    whole_config["docker_enabled"] = false;
  }

  bool docker_enabled = whole_config["docker_enabled"];

  if (!whole_config["use_router"].is_boolean()){
    whole_config["use_router"] = true;
  }

  bool use_router = whole_config["use_router"];
  
  nlohmann::json::iterator tc = whole_config.find("testcases");
  assert (tc != whole_config.end());
  
  int testcase_num = 0;
  for (typename nlohmann::json::iterator itr = tc->begin(); itr != tc->end(); itr++,testcase_num++){
    std::string title = whole_config["testcases"][testcase_num].value("title","BAD_TITLE");

    nlohmann::json this_testcase = whole_config["testcases"][testcase_num];

    if (!this_testcase["use_router"].is_boolean()){
      this_testcase["use_router"] = use_router;
    }


    nlohmann::json commands = nlohmann::json::array();
    // if "command" exists in whole_config, we must wrap it in a container.
    bool found_commands = false;
    if(this_testcase.find("command") != this_testcase.end()){
      found_commands = true;
      if (this_testcase["command"].is_array()){
        commands = this_testcase["command"];
      }
      else{
        commands.push_back(this_testcase["command"]);
      }

      this_testcase.erase("command");
    }

    assert (this_testcase["containers"].is_null() || !found_commands);

    if(!this_testcase["containers"].is_null()){
      assert(this_testcase["containers"].is_array());
    }

    if(this_testcase["containers"].is_null()){
      this_testcase["containers"] = nlohmann::json::array();
      //commands may have to be a json::array();
      this_testcase["containers"][0] = nlohmann::json::object();
      this_testcase["containers"][0]["commands"] = commands;
    }

    //get the testcase type
    std::string testcase_type = this_testcase.value("type","Execution");
    if (testcase_type == "Compilation"){
      assert(this_testcase["containers"].size() == 1);
    }

    if(this_testcase["containers"].size() > 1){
      assert(this_testcase["containers"].size() == 1 || docker_enabled == true);
    }

    for (int container_num = 0; container_num < this_testcase["containers"].size(); container_num++){
      if(this_testcase["containers"][container_num]["commands"].is_string()){
        std::string this_command = this_testcase["containers"][container_num].value("commands", "");
        this_testcase["containers"][container_num].erase("commands");
        this_testcase["containers"][container_num]["commands"] = nlohmann::json::array();
        this_testcase["containers"][container_num]["commands"].push_back(this_command);
      }

      if(this_testcase["containers"][container_num]["container_name"].is_null()){
        //pad this out correctly?
        this_testcase["containers"][container_num]["container_name"] = "container" + std::to_string(container_num); 
      }

      if(this_testcase["containers"][container_num]["outgoing_connections"].is_null()){
        this_testcase["containers"][container_num]["outgoing_connections"] = nlohmann::json::array();
      }

      if(this_testcase["containers"][container_num]["container_image"].is_null()){
        //TODO: store the default system image somewhere and fill it in here.
        this_testcase["containers"][container_num]["container_image"] = "ubuntu:custom";
      }    
    }
    whole_config["testcases"][testcase_num] = this_testcase;
    assert(!whole_config["testcases"][testcase_num]["title"].is_null());
    assert(!whole_config["testcases"][testcase_num]["containers"].is_null());
  }
}

void FormatDispatcherActions(nlohmann::json &whole_config) {

  bool docker_enabled = whole_config["docker_enabled"];

  nlohmann::json::iterator tc = whole_config.find("testcases");
  assert (tc != whole_config.end());

  int testcase_num = 0;
  for (typename nlohmann::json::iterator itr = tc->begin(); itr != tc->end(); itr++,testcase_num++){

    nlohmann::json this_testcase = whole_config["testcases"][testcase_num];

    if(this_testcase["dispatcher_actions"].is_null()){
      whole_config["testcases"][testcase_num]["dispatcher_actions"] = nlohmann::json::array();
      continue;
    }

    std::vector<nlohmann::json> dispatcher_actions = mapOrArrayOfMaps(this_testcase, "dispatcher_actions");

    if(dispatcher_actions.size() > 0){
      assert(docker_enabled);
    }

    for (int i = 0; i < dispatcher_actions.size(); i++){
      nlohmann::json dispatcher_action = dispatcher_actions[i];

      std::string action = dispatcher_action.value("action","");
      std::cout << "we just got action " << action << std::endl;
      assert(action != "");

      if(action == "delay"){
        assert(!dispatcher_action["seconds"].is_null());
        assert(!dispatcher_action["delay"].is_string());

        float delay_time_in_seconds = 1.0;
        delay_time_in_seconds = float(dispatcher_action.value("seconds",1.0));
        dispatcher_action["seconds"] = delay_time_in_seconds;
      }else if(action == "stdin"){
        assert(!dispatcher_action["string"].is_null());
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

        whole_config["testcases"][testcase_num]["dispatcher_actions"][i] = dispatcher_action;
      }
    }
  }
}


void RewriteDeprecatedMyersDiff(nlohmann::json &whole_config) {

  nlohmann::json::iterator tc = whole_config.find("testcases");
  if (tc == whole_config.end()) { /* no testcases */ return; }

  // loop over testcases
  int which_testcase = 0;
  for (nlohmann::json::iterator my_testcase = tc->begin();
       my_testcase != tc->end(); my_testcase++,which_testcase++) {
    nlohmann::json::iterator validators = my_testcase->find("validation");
    if (validators == my_testcase->end()) { /* no autochecks */ continue; }

    // loop over autochecks
    for (int which_autocheck = 0; which_autocheck < validators->size(); which_autocheck++) {
      nlohmann::json& autocheck = (*validators)[which_autocheck];
      std::string method = autocheck.value("method","");

      // if autocheck is old myersdiff format...  rewrite it!
      if (method == "myersDiffbyLinebyChar") {
        autocheck["method"] = "diff";
        assert (autocheck.find("comparison") == autocheck.end());
        autocheck["comparison"] = "byLinebyChar";
      } else if (method == "myersDiffbyLinebyWord") {
        autocheck["method"] = "diff";
        assert (autocheck.find("comparison") == autocheck.end());
        autocheck["comparison"] = "byLinebyWord";
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

void InflateTestcases(nlohmann::json &whole_config){
  nlohmann::json::iterator tc = whole_config.find("testcases");
  assert (tc != whole_config.end());
  
  int testcase_num = 0;
  for (typename nlohmann::json::iterator itr = tc->begin(); itr != tc->end(); itr++,testcase_num++){
      nlohmann::json this_testcase = whole_config["testcases"][testcase_num];
      InflateTestcase(this_testcase, whole_config);
      whole_config["testcases"][testcase_num] = this_testcase;
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
  
  AddDockerConfiguration(answer);
  FormatDispatcherActions(answer);
  AddSubmissionLimitTestCase(answer);
  AddAutogradingConfiguration(answer);

  if (rcsid != "") {
    CustomizeAutoGrading(rcsid,answer);
  }

  RewriteDeprecatedMyersDiff(answer);

  InflateTestcases(answer);
  
  return answer;
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
    } else if (program_name == "/usr/bin/java") {
      j["description"] = "syntax error output from running java";
    } else if (program_name == "/usr/bin/javac") {
      j["description"] = "syntax error output from running javac";
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