#include <iostream>
#include <fstream>

#include "execute.h"
#include "TestCase.h"
#include "default_config.h"

/*

  Generates a file in json format containing all of the information defined in
  config.json for easier parsing.

*/

// =====================================================================
// =====================================================================

nlohmann::json printTestCase(TestCase test) {
  nlohmann::json j;
  j["title"] = "Test " + std::to_string(test.getID()) + " " + test.getTitle();
  j["testcase_label"] = test.getTestcaseLabel();
  j["details"] = test.getDetails();
  j["points"] = test.getPoints();
  j["extra_credit"] = test.getExtraCredit();
  j["hidden"] = test.getHidden();
  j["view_testcase_message"] = test.viewTestcaseMessage();

  // THESE ELEMENTS ARE DEPRECATED / NEED TO BE REPLACED
  j["view_file_results"] = true;
  j["view_test_points"] = true;
  j["view_file"] = "";
  return j;
}

int main(int argc, char *argv[]) {

  // LOAD HW CONFIGURATION JSON
  nlohmann::json config_json = LoadAndProcessConfigJSON("");  // don't know the username yet

  nlohmann::json j;

  if (argc != 2) {
    std::cout << "USAGE: " << argv[0] << " [output_file]" << std::endl;
    return 0;
  }

  std::cout << "FILENAME " << argv[0] << std::endl;
  int total_nonec = 0;
  int total_ec = 0;

  int visible = 0;
  std::cout << "config.json.size " << config_json.size() << std::endl;
  nlohmann::json::iterator tc = config_json.find("testcases");
  
  assert (tc != config_json.end());

  int max_submissions = MAX_NUM_SUBMISSIONS;

  nlohmann::json all;
  int which_testcase = 0;

  int total_time = 0;
  //The base max time per testcase.
  int base_time = default_limits.find(RLIMIT_CPU)->second;

  int leeway = CPU_TO_WALLCLOCK_TIME_BUFFER;

  nlohmann::json::iterator rl = config_json.find("resource_limits");
  if (rl != config_json.end()){
    base_time = rl->value("RLIMIT_CPU", base_time);
  }
  std::cerr << "BASE TIME " << base_time << std::endl;

  for (typename nlohmann::json::iterator itr = tc->begin(); itr != tc->end(); itr++,which_testcase++) {
    int points = itr->value("points",0);
    bool extra_credit = itr->value("extra_credit",false);
    bool hidden = itr->value("hidden",false);

    //Add this textcases worst case time to the total worst case time.
    int cpu_time = base_time;
    nlohmann::json::iterator rl = itr->find("resource_limits");
    if (rl != itr->end()){
      cpu_time = rl->value("RLIMIT_CPU", base_time);
    }
    cpu_time += leeway;
    total_time += cpu_time;

    if (points > 0) {
      if (!extra_credit)
        total_nonec += points;
      else
        total_ec += points;
      if (!hidden)
        visible += points;
    }
    //container name only matters if we try to get the commands for this testcase.
    std::string container_name = "";
    TestCase tc(config_json,which_testcase,container_name);
    if (tc.isSubmissionLimit()) {
      max_submissions = tc.getMaxSubmissions();
    }
    all.push_back(printTestCase(tc)); 
  }
  std::cout << "processed " << all.size() << " test cases" << std::endl;
  j["num_testcases"] = all.size();
  j["testcases"] = all;
  j["max_possible_grading_time"] = total_time;
  std::string start_red_text = "\033[1;31m";
  std::string end_red_text   = "\033[0m";

  nlohmann::json grading_parameters = config_json.value("grading_parameters",nlohmann::json::object());
  int AUTO_POINTS         = grading_parameters.value("AUTO_POINTS",total_nonec);
  int EXTRA_CREDIT_POINTS = grading_parameters.value("EXTRA_CREDIT_POINTS",total_ec);
  int TA_POINTS           = grading_parameters.value("TA_POINTS",0);
  int TOTAL_POINTS        = grading_parameters.value("TOTAL_POINTS",AUTO_POINTS+TA_POINTS);

  if (total_nonec != AUTO_POINTS) {
    std::cout << "\n" << start_red_text << "ERROR: Automated Points do not match testcases." << total_nonec 
        << "!=" << AUTO_POINTS << end_red_text << "\n" << std::endl;
    return 1;
  }

  if (total_ec != EXTRA_CREDIT_POINTS) {
    std::cout << "\n" << start_red_text << "ERROR: Extra Credit Points do not match testcases." << total_ec 
        << "!=" << EXTRA_CREDIT_POINTS << end_red_text << "\n" << std::endl;
    return 1;
  }
  if (total_nonec + TA_POINTS != TOTAL_POINTS) {
    std::cout << "\n" << start_red_text << "ERROR: Automated Points and TA Points do not match total." 
        << end_red_text << "\n" << std::endl;
    return 1;
  }


  std::string id = getAssignmentIdFromCurrentDirectory(std::string(argv[0]));
  //std::vector<std::string> part_names = PART_NAMES;

  
  j["id"] = id;
  if (config_json.find("assignment_message") != config_json.end()) {
    j["gradeable_message"] = config_json.value("assignment_message",""); 
  } else if (config_json.find("gradeable_message") != config_json.end()) {
    j["gradeable_message"] = config_json.value("gradeable_message", "");
  }
  if (config_json.find("early_submission_incentive") != config_json.end()) {
    nlohmann::json early_submission_incentive = config_json.value("early_submission_incentive",nlohmann::json::object());
    nlohmann::json incentive;
    incentive["message"] = early_submission_incentive.value("message","");
    incentive["minimum_days_early"] = early_submission_incentive.value("minimum_days_early",0);
    incentive["minimum_points"] = early_submission_incentive.value("minimum_points",0);
    incentive["test_cases"] = early_submission_incentive.value("test_cases",std::vector<int>());
    j["early_submission_incentive"] = incentive; 
  }
  j["max_submissions"] = max_submissions;
  j["max_submission_size"] = config_json.value("max_submission_size",MAX_SUBMISSION_SIZE);

  j["required_capabilities"] = config_json.value("required_capabilities","default");
  nlohmann::json::iterator parts = config_json.find("part_names");
  if (parts != config_json.end()) {
    j["part_names"] =  nlohmann::json::array();
    for (int i = 0; i < parts->size(); i++) {
      j["part_names"].push_back((*parts)[i]);
    }
  }

  // Configure defaults for hide_submitted_files
  j["hide_submitted_files"] = config_json.value("hide_submitted_files", false);

  // Configure defaults for hide_version_and_test_details
  j["hide_version_and_test_details"] = config_json.value("hide_version_and_test_details", false);

    /******************************************

    Validate and inflate notebook data

    ******************************************/

  nlohmann::json::iterator in_notebook_cells = config_json.find("notebook");
  nlohmann::json::iterator itempool_definitions = config_json.find("item_pool");
  if (in_notebook_cells != config_json.end()){

    // Setup "notebook" items inside the 'j' json item that will be passed forward
    j["notebook"] = nlohmann::json::array();

    for (int i = 0; i < in_notebook_cells->size(); i++){
      nlohmann::json out_notebook_cell;
      nlohmann::json in_notebook_cell = (*in_notebook_cells)[i];

      // Get type field
      std::string type = in_notebook_cell.value("type", "");
      assert(type != "");
      out_notebook_cell["type"] = type;

      // Get testcase_ref if it exists
      std::string testcase_ref = in_notebook_cell.value("testcase_ref", "");
      if(testcase_ref != ""){
        out_notebook_cell["testcase_ref"] = testcase_ref;
      }

      // Handle each specific note book cell type
      // Handle markdown data
      if(type == "markdown"){
          // Get markdown items
          std::string markdown_string = in_notebook_cell.value("markdown_string", "");
          std::string markdown_file = in_notebook_cell.value("markdown_file", "");

          // Assert only one was passed in
          assert(
                  (markdown_string != "" && markdown_file == "") ||
                  (markdown_string == "" && markdown_file != "")
                  );

          // Pass forward the item that was passed in
          if(markdown_string != ""){
              out_notebook_cell["markdown_string"] = markdown_string;
          }
          else{
              out_notebook_cell["markdown_file"] = markdown_file;
          }
      }

      // Handle image data
      else if(type == "image"){
          // Get req image items
          std::string image = in_notebook_cell.value("image", "");

          // Assert req fields were not empty
          assert(image != "");

          // Get optional image items
          int height = in_notebook_cell.value("height", 0);
          int width = in_notebook_cell.value("width", 0);
          std::string alt_text = in_notebook_cell.value("alt_text", "Instructor provided image");

          // Pass forward populated items
          out_notebook_cell["image"] = image;
          out_notebook_cell["alt_text"] = alt_text;

          if(height > 0){
              out_notebook_cell["height"] = height;
          }

          if(width > 0){
              out_notebook_cell["width"] = width;
          }
      }

      // Handle short_answer data
      else if(type == "short_answer"){
          // Get req short_answer items
          std::string filename = in_notebook_cell.value("filename", "");

          // Assert req fields were not empty
          assert(filename != "");

          // Get optional short_answer items
          std::string initial_value = in_notebook_cell.value("initial_value", "");
          std::string programming_language = in_notebook_cell.value("programming_language", "");
          int rows = in_notebook_cell.value("rows", 0);

          // Pass forward populated items
          out_notebook_cell["filename"] = filename;
          out_notebook_cell["initial_value"] = initial_value;
          out_notebook_cell["rows"] = rows;

          if(programming_language != ""){
              out_notebook_cell["programming_language"] = programming_language;
          }
      }

      // Handle multiple choice data
      else if(type == "multiple_choice"){
          // Get req multiple choice items
          std::string filename = in_notebook_cell.value("filename", "");

          // Assert filename was present
          assert(filename != "");

          // Get choices
          nlohmann::json choices = in_notebook_cell.value("choices", nlohmann::json::array());

          int num_of_choices = 0;
          for (auto it = choices.begin(); it != choices.end(); ++it){
              // Reassign the value of this iteration to choice
              nlohmann::json choice = it.value();

              // Get value and description
              std::string value = choice.value("value", "");
              std::string description = choice.value("description", "");

              // Assert choice value and description were in fact present
              assert(value != "");
              assert(description != "");

              num_of_choices++;
          }

          // Assert choices was not empty
          assert(num_of_choices > 0);

          bool allow_multiple = in_notebook_cell.value("allow_multiple", false);
          bool randomize_order = in_notebook_cell.value("randomize_order", false);

          // Pass forward items
          out_notebook_cell["filename"] = filename;
          out_notebook_cell["choices"] = choices;
          out_notebook_cell["allow_multiple"] = allow_multiple;
          out_notebook_cell["randomize_order"] = randomize_order;
      }
      else if(type == "item"){
        std::string item_label = in_notebook_cell.value("item_label", "");

        // Update the complete_config if we had a blank label
        config_json["notebook"][i]["item_label"] = "";

        if(itempool_definitions == config_json.end()){
          std::cout << "ERROR: Found an \"item\" cell but no global item_pool was defined!" << std::endl;
          throw -1;
        }
        //Search through the global item_pool to find if the items in this itempool exist
        if(in_notebook_cell.find("from_pool") == in_notebook_cell.end()){
          std::cout << "ERROR: item with label " << (item_label.empty() ? "\"[no label]\"" : item_label)
                    << " does not have a from_pool" << std::endl;
          throw -1;
        }
        nlohmann::json in_notebook_cell_from_pool = in_notebook_cell.value("from_pool", nlohmann::json::array());
        //std::cout << "Checking for " << in_notebook_cell_item_pool.size() << " items among a global set of "
        //          << itempool_definitions->size() << " items" << std::endl;

        if(in_notebook_cell_from_pool.size() == 0){
          std::cout << "ERROR: item with label " << (item_label.empty() ? "\"[no label]\"" : item_label)
                    << " has an empty from_pool, requires at least one item!" << std::endl;
          throw -1;
        }

        for(int j=0; j<in_notebook_cell_from_pool.size(); j++){
          bool found_global_itempool_item = false;
          for(int k=0; k<itempool_definitions->size(); k++) {
            if ((*itempool_definitions)[k]["item_name"] == in_notebook_cell_from_pool[j]) {
              found_global_itempool_item = true;
              break;
            }
          }
          if(!found_global_itempool_item){
            std::cout << "ERROR: item with label \"" << (item_label.empty() ? "[no label]" : item_label);
            std::cout << "\" requested undefined item: " << in_notebook_cell_from_pool[j] << std::endl;
            throw -1;
          }
          /*else{
            std::cout << "Found global itempool item " << in_notebook_cell_from_pool[j] << " for item with label: "
                      << (item_label.empty() ? "[no label]" : item_label) << std::endl;
          }*/
        }

        // Write the empty string if no label provided, otherwise pass forward item_label
        out_notebook_cell["item_label"] = item_label;
        // Pass forward other items
        out_notebook_cell["from_pool"] = in_notebook_cell["from_pool"];
        if(in_notebook_cell.find("points") != in_notebook_cell.end()){
          out_notebook_cell["points"] = in_notebook_cell["points"];
        }
      }

      // Else unknown type was passed in throw exception
      else{
            throw "An unknown notebook cell 'type' was detected in the supplied config.json file. Build failed.";
      }

      // Add this newly validated notebook cell to the one being sent forward
      assert(type != "item" || out_notebook_cell.find("item_label") != out_notebook_cell.end());
      j["notebook"].push_back(out_notebook_cell);

    }
  }

  // By default, we have one drop zone without a part label / sub
  // directory.

  // But, if there are input fields, but there are no explicit parts
  // (drag & drop zones / "bucket"s for file upload), set part_names
  // to an empty array (no zones for file drag & drop).
  if (parts == config_json.end() &&
      in_notebook_cells != config_json.end()) {
    j["part_names"] =  nlohmann::json::array();
  }

  j["auto_pts"] = AUTO_POINTS;
  j["points_visible"] = visible;
  j["ta_pts"] = TA_POINTS;
  j["total_pts"] = TOTAL_POINTS;
  

  // =================================================================================
  // EXPORT THE JSON FILE

  std::ofstream init;
  init.open(argv[1], std::ios::out);
  if (!init.is_open()) {
    std::cout << "\n" << start_red_text << "ERROR: unable to open new file for initialization... Now Exiting" 
        << end_red_text << "\n" << std::endl;
    return 0;
  }
  init << j.dump(4) << std::endl;
  // -----------------------------------------------------------------------
  // Also, write out the config file with automatic defaults (for debugging)
  std::string complete_config_file = argv[1];
  int b_pos = complete_config_file.find("/build/build_");
  // If we are not in the test suite
  if (b_pos != std::string::npos) {
    complete_config_file = complete_config_file.substr(0,b_pos) +
      "/complete_config/complete_config_"+ complete_config_file.substr(b_pos+13,complete_config_file.size()-b_pos-13);
    std::string mkdir_command = "mkdir -p " + complete_config_file.substr(0,b_pos) + "/complete_config/";
    system (mkdir_command.c_str());
    std::ofstream complete_config;
    complete_config.open(complete_config_file, std::ios::out);
    complete_config << config_json.dump(4) << std::endl;
  }
  // If we are in the test suite
  else{
    int b_pos = complete_config_file.find("/data/");
    if (b_pos != std::string::npos) {
      complete_config_file = complete_config_file.substr(0,b_pos) +
        "/assignment_config/complete_config.json";
      std::ofstream complete_config;
      complete_config.open(complete_config_file, std::ios::out);
      complete_config << config_json.dump(4) << std::endl;
    }

  }
  return 0;
}
