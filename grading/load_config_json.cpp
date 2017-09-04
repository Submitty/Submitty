#include <unistd.h>
#include <cstdlib>
#include <string>
#include <iostream>
#include <cassert>

#include "TestCase.h"
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

  whole_config["autograding"]["compilation_to_validation"].push_back("test*.txt");

  whole_config["autograding"]["submission_to_validation"].push_back("**/README.txt");
  whole_config["autograding"]["submission_to_validation"].push_back("textbox_*.txt");
  whole_config["autograding"]["submission_to_validation"].push_back("**/*.pdf");

  whole_config["autograding"]["work_to_details"].push_back("test*.txt");
  whole_config["autograding"]["work_to_details"].push_back("test*_diff.json");
  whole_config["autograding"]["work_to_details"].push_back("**/README.txt");
  whole_config["autograding"]["work_to_details"].push_back("textbox_*.txt");

  if (whole_config["autograding"].find("use_checkout_subdirectory") == whole_config["autograding"].end()) {
    whole_config["autograding"]["use_checkout_subdirectory"] = "";
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


// =====================================================================
// =====================================================================

nlohmann::json LoadAndProcessConfigJSON(const std::string &rcsid) {

  nlohmann::json answer;
  std::stringstream sstr(GLOBAL_config_json_string);
  sstr >> answer;

  AddSubmissionLimitTestCase(answer);
  AddAutogradingConfiguration(answer);
  if (rcsid != "") {
    CustomizeAutoGrading(rcsid,answer);
  }

  RewriteDeprecatedMyersDiff(answer);

  std::cout << "JSON PARSED" << std::endl;
  
  return answer;
}
