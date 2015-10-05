#include <iostream>
#include <iomanip>
#include <fstream>

#include "json.hpp"

#include "TestCase.h"
#include "default_config.h"

using json = nlohmann::json;

/*

  Generates a file in json format containing all of the information defined in
  config.h for easier parsing.

*/


// =====================================================================
// =====================================================================

void printTestCase(std::ostream &out, TestCase test) {
  json j = {
	  {"title", test.title()},
	  {"details", test.details()},
	  {"points", test.points()},
	  {"hidden", test.hidden()},
	  {"extracredit", test.extracredit()},
	  {"visible", test.visible()},
	  {"view_test_points", test.view_test_points()},
	  {"view_file", test.getView_file()},
	  {"view_file_results", test.getView_file_results()},
  };

  out << std::setw(4) << j;
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

  init << "{\n\t\"id\": \"" << id << "\"," << std::endl;
  init << "\t\"assignment_message\": \"" << ASSIGNMENT_MESSAGE << "\"," << std::endl;

  init << "\t\"max_submissions\": " << MAX_NUM_SUBMISSIONS << "," << std::endl;
  init << "\t\"max_submission_size\": " << MAX_SUBMISSION_SIZE << "," << std::endl;

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
