#include <cassert>
#include <unistd.h>
#include "PacmanGrader.h"

// =============================================================================
// =============================================================================

TestResults* PacmanGrader_doit (const TestCase &tc, const nlohmann::json& j) {

  // open the specified runtime Pacman output/log file
  std::string filename = j.value("actual_file","");
  std::ifstream pacman_output((tc.getPrefix()+"_"+filename).c_str());

  // check to see if the file was opened successfully
  if (!pacman_output.good()) {
    return new TestResults(0.0,{std::make_pair("ERROR: Pacman output does not exist","failure")});
  }

  // instructor must provided correct expected number of tests
  int num_pacman_tests = j.value("num_tests",-1);
  if (num_pacman_tests <= 0) {
    return new TestResults(0.0,{std::make_pair("CONFIGURATION ERROR: Must specify number of Pacman tests","failure")});
  }

  // store the points information
  std::vector<int> awarded(num_pacman_tests,-1);
  std::vector<int> possible(num_pacman_tests,-1);
  int total_awarded = -1;
  int total_possible = -1;

  std::vector<std::pair<std::string, std::string> > messages;
  std::string line;
  while (getline(pacman_output,line)) {
    std::stringstream line_ss(line);
    std::string word;
    while (line_ss >> word) {
      if (word == "###") {
        // parse each question score
        line_ss >> word;
        if (word == "Question") {
          line_ss >> word;
          int which = atoi(word.substr(1,word.size()-1).c_str())-1;
          if (num_pacman_tests < 0 || which >= num_pacman_tests) {
            messages.push_back(std::make_pair("ERROR: Invalid question number " + word,"failure"));
            return new TestResults(0.0,messages);
          }
          char c;
          line_ss >> awarded[which] >> c >> possible[which];
          if (awarded[which] < 0 ||
              c != '/' ||
              possible[which] <= 0 ||
              awarded[which] > possible[which]) {
            messages.push_back(std::make_pair("ERROR: Could not parse question points","failure"));
            return new TestResults(0.0,messages);
          }
        }
      } else if (word == "Total:") {
        // parse the total points
        char c;
        line_ss >> total_awarded >> c >> total_possible;
        if (total_awarded < 0 ||
            c != '/' ||
            total_possible <= 0 ||
            total_awarded > total_possible) {
          messages.push_back(std::make_pair("ERROR: Could not parse total points","failure"));
          return new TestResults(0.0,messages);
        }
      }
    }
  }

  // error checking
  int check_awarded = 0;
  int check_possible = 0;
  for (int i = 0; i < num_pacman_tests; i++) {
    if (awarded[i] < 0 ||
        possible[i] < 0) {
      messages.push_back(std::make_pair("ERROR: Missing question " + std::to_string(i+1),"failure"));
    } else {
      check_awarded += awarded[i];
      check_possible += possible[i];
      messages.push_back(std::make_pair("Question " + std::to_string(i+1) + ": " 
       + std::to_string(awarded[i]) + " / " 
       + std::to_string(possible[i]),"failure"));
    }
  }
  if (total_possible == -1 ||
      total_awarded == -1) {
    messages.push_back(std::make_pair("ERROR: Could not parse total points","failure"));
    return new TestResults(0.0,messages);
  }
  if (total_possible != check_possible ||
      total_awarded != check_awarded) {
    messages.push_back(std::make_pair("ERROR: Summation of parsed points does not match","failure"));
    return new TestResults(0.0,messages);
  }

  // final answer
  messages.push_back(std::make_pair("Total: " + std::to_string(total_awarded) + " / " + std::to_string(total_possible),"failure"));
  return new TestResults(float(total_awarded) / float(total_possible),messages);
}

// =============================================================================

