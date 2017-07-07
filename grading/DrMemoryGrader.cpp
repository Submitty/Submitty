#include <cassert>
#include <unistd.h>
#include "DrMemoryGrader.h"

// =============================================================================
// =============================================================================

TestResults* DrMemoryGrader_doit (const TestCase &tc, const nlohmann::json& j) {

  // open the specified runtime DrMemory output/log file
  std::string filename = j.value("actual_file","");
  std::ifstream drmemory_output((tc.getPrefix()+"_"+filename).c_str());

  // check to see if the file was opened successfully
  if (!drmemory_output.good()) {
    return new TestResults(0.0,{std::make_pair("ERROR: DrMemory output does not exist","failure")});
  }

  std::vector<std::vector<std::string> > file_contents;
  std::string line;
  while (getline(drmemory_output,line)) {
    file_contents.push_back(std::vector<std::string>());
    std::stringstream ss(line);
    std::string token;
    while(ss >> token) {
      file_contents.back().push_back(token);
    }
  }

  int num_errors = 0;
  bool errors_message = false;
  bool no_errors_message = false;
  int zero_unique_errors = 0;
  bool non_zero_unique_errors = false;

  for (int i = 0; i < file_contents.size(); i++) {
    if (file_contents[i].size() >= 3 &&
        file_contents[i][0] == "~~Dr.M~~" &&
        file_contents[i][1] == "Error" &&
        file_contents[i][2][0] == '#') {
      num_errors++;
    }
    if (file_contents[i].size() == 4 &&
        file_contents[i][0] == "~~Dr.M~~" &&
        file_contents[i][1] == "ERRORS" &&
        file_contents[i][2] == "FOUND") {
      errors_message = true;
    }
    if (file_contents[i].size() == 4 &&
        file_contents[i][0] == "~~Dr.M~~" &&
        file_contents[i][1] == "NO" &&
        file_contents[i][2] == "ERRORS" &&
        file_contents[i][3] == "FOUND:") {
      no_errors_message = true;
    }

    if (file_contents[i].size() >= 3 &&
        file_contents[i][0] == "~~Dr.M~~" &&
        file_contents[i][2] == "unique,") {
      if (file_contents[i][1] == "0") {
        zero_unique_errors++;
      } else {
        non_zero_unique_errors = true;
      }
    }
  }

  float result = 1.0;
  std::vector<std::pair<std::string, std::string> > messages;

  if (num_errors > 0) {
    messages.push_back(std::make_pair(std::to_string(num_errors) + " DrMemory Errors","failure"));
    result = 0;
  }
  if (result > 0.01 && 
      (no_errors_message == false ||
       non_zero_unique_errors == true ||
       zero_unique_errors != 6)) {
    messages.push_back(std::make_pair("Program Contains Memory Errors","failure"));
    result = 0;
  }
  if (no_errors_message == true &&
      result < 0.99) {
    messages.push_back(std::make_pair("Your Program *does* contains memory errors (misleading DrMemory Output \"NO ERRORS FOUND\")","failure"));
  }


  return new TestResults(result,messages);
}

// =============================================================================

