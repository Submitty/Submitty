#include <iostream>
#include <sstream>
#include <string>
#include <vector>
#include <cstdlib>

#include "grading/TestCase.h"
#include "grading/json.hpp"

TestResults* TestCase::custom_dispatch(const nlohmann::json& grader) const {

  std::string method = grader.value("method","");
  assert (method == "custom");

  // ========================================
  // GRAB THE COMMAND LINE ARG
  int num;
  std::string args = grader.value("args","");
  try {
    num = std::stoi(args.c_str());
    if (num <= 0) throw -1;
  } catch (...) {
    return new TestResults(0.0,{std::make_pair("ERROR! args should be > 1 (specify number of values to sum)","failure")});
  }

  // ========================================
  // OPEN THE STUDENT OUTPUT FILES
  std::vector<std::string> filenames = stringOrArrayOfStrings(grader,"actual_file");
  std::vector<std::string> contents_of_files;
  for (int i = 0; i < filenames.size(); i++) {
    std::string file_contents;
    std::string f = this->getPrefix() + "_" + filenames[i];
    if (!getFileContents(f,file_contents)) {
      return new TestResults(0.0,{std::make_pair("ERROR!  Could not open student file: '" + f + "'","failure")});
    }    
    contents_of_files.push_back(file_contents);
  }


  // rubric items to check
  bool correct_total = false;
  bool correct_num = true;
  bool is_random = true;
  bool found_total_string = false;


  // ========================================
  // LOOP OVER ALL FILES
  for (int x = 0; x < contents_of_files.size(); x++) {
    std::stringstream ss(contents_of_files[x]);
    std::string token;
    int computed_total = 0;
    int num_values = 0;
    int last_value = 0;
    while (ss >> token) {
      if (token == "total") {
        found_total_string = true;
        break;
      }
      try {
        last_value = std::stoi(token.c_str());
      } catch (...) {
        return new TestResults(0.0,{std::make_pair("ERROR! parser error!","failure")});
      }
      computed_total += last_value;
      num_values++;
    }
    int total;
    if (found_total_string) {
      ss >> token;
      if (token != "=") { found_total_string = false; }
      if (!(ss >> token)) { found_total_string = false; }
      try {
        total = std::stoi(token.c_str());
      } catch (...) {
        return new TestResults(0.0,{std::make_pair("ERROR! could not parse total value as an integer","failure")});
      }
    }
    if (!found_total_string) {
      // if the total string is missing, use the last value as the total
      total = last_value;
      computed_total -= last_value;
      num_values--;
    }
    if (num_values > 0 && total == computed_total) { 
      correct_total = true;
    }
    if (num_values != num) { 
      correct_num = false; 
    }
    if (x != 0 && contents_of_files[0] == contents_of_files[x]) {
      is_random = false;
    }
  }
  
  
  // ========================================
  // PREPARE THE GRADE & ERROR MESSAGE(S)
  std::vector<std::pair<std::string, std::string> > messages;
  float grade = 1.0;

  if (!found_total_string) {
    messages.push_back(std::make_pair("ERROR!  MISSING \"total = \" string","failure")); 
    grade -= 0.2;
  }
  if (!correct_total)  {
    messages.push_back(std::make_pair("ERROR!  INCORRECT TOTAL","failure"));
    grade -= 0.4;
  }
  if (!correct_num) {
    grade -= 0.4;
    messages.push_back(std::make_pair("ERROR!  WRONG NUMBER OF VALUES","failure")); 
  }
  if (!is_random) {
    grade -= 0.5;
    messages.push_back(std::make_pair("ERROR!  NOT RANDOM","failure")); 
  }
  grade = std::max(0.0f,grade);
  return new TestResults(grade,messages);
}
