#include <iostream>
#include <fstream>

#include "TestCase.h"
#include "default_config.h"


/*

  Generates a file in json format containing all of the information defined in
  config.h for easier parsing.

*/


// =====================================================================
// =====================================================================

void printTestCase(std::ostream &out, TestCase test) {
  std::string hidden = (test.hidden()) ? "true" : "false";
  std::string extracredit = (test.extracredit()) ? "true" : "false";
  std::string visible = (test.visible()) ? "true" : "false";
  std::string view_test_points = (test.view_test_points()) ? "true" : "false";
  std::string view_file = '"'+(test.getView_file())+'"';
  std::string view_file_results = (test.getView_file_results())? "true" : "false";
  out << "\t{" << std::endl;
  out << "\t\t\"title\": \"" << test.title() << "\"," << std::endl;
  out << "\t\t\"details\": \"" << test.details() << "\"," << std::endl;
  out << "\t\t\"points\": " << test.points() << "," << std::endl;
  out << "\t\t\"hidden\": " << hidden << "," << std::endl;
  out << "\t\t\"extracredit\": " << extracredit << "," << std::endl;
  out << "\t\t\"visible\": " << visible << "," << std::endl;
  out << "\t\t\"view_test_points\": " << view_test_points << "," << std::endl;
  out << "\t\t\"view_file\": " << view_file << "," << std::endl;
  out << "\t\t\"view_file_results\": " << view_file_results << "," << std::endl;

  //  out << "\t\t\"expected_output\": "
  //   << "\"" << test.expected(0) << "\"" << std::endl;
  out << "\t}";
}

int main(int argc, char *argv[]) {


  std::cout << "@configure main : assignment_limits size " << assignment_limits.size() << std::endl;
  assert (assignment_limits.size() == 16);


  if (argc != 2) {
    std::cout << "USAGE: " << argv[0] << " [output_file]" << std::endl;
    return 0;
  }
  std::cout << "FILENAME " << argv[0] << std::endl;
  int total_nonec = 0;
  int total_ec = 0;
  for (unsigned int i = 0; i < testcases.size(); i++) {

    if (testcases[i].extracredit())
      total_ec += testcases[i].points();
    else
      total_nonec += testcases[i].points();
  }

  std::string start_red_text = "\033[1;31m";
  std::string end_red_text   = "\033[0m";

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

  std::ofstream init;
  init.open(argv[1], std::ios::out);

  if (!init.is_open()) {
    std::cout << "\n" << start_red_text << "ERROR: unable to open new file for initialization... Now Exiting" 
	      << end_red_text << "\n" << std::endl;
    return 0;
  }

  std::string id = getAssignmentIdFromCurrentDirectory(std::string(argv[0]));
  std::vector<std::string> part_names = PART_NAMES;

  init << "{\n\t\"id\": \"" << id << "\"," << std::endl;
  init << "\t\"assignment_message\": \"" << ASSIGNMENT_MESSAGE << "\"," << std::endl;

  init << "\t\"max_submissions\": " << MAX_NUM_SUBMISSIONS << "," << std::endl;
  init << "\t\"max_submission_size\": " << MAX_SUBMISSION_SIZE << "," << std::endl;

  if (part_names.size() > 0) {
    init << "\t\"num_parts\": " << part_names.size() << "," << std::endl;
    init << "\t\"part_names\": [" << std::endl;
    for (int i = 0; i < part_names.size(); i++) {
      init << "\t\t\"" << part_names[i] << "\"";
      if (i != part_names.size()-1) 
        init << ",";
      init << std::endl;
    }
    init << "\t]," << std::endl;
  }

  init << "\t\"auto_pts\": " << AUTO_POINTS << "," << std::endl;
  int visible = 0;
  for (unsigned int i = 0; i < testcases.size(); i++) {
    if (!testcases[i].hidden())
      visible += testcases[i].points();
  }
  init << "\t\"points_visible\": " << visible << "," << std::endl;
  init << "\t\"ta_pts\": " << TA_POINTS << "," << std::endl;
  init << "\t\"total_pts\": " << TOTAL_POINTS << "," << std::endl;

  init << "\t\"num_testcases\": " << testcases.size() << "," << std::endl;

  init << "\t\"testcases\": [" << std::endl;

  for (unsigned int i = 0; i < testcases.size(); i++) {
    printTestCase(init, testcases[i]);
    if (i != testcases.size() - 1)
      init << "," << std::endl;
  }

  init << " ]\n}" << std::endl;

  init.close();

  return 0;
}
