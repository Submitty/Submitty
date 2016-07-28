#include <iostream>
#include <sstream>
#include <string>
#include <vector>
#include <cstdlib>

#include "grading/TestCase.h"
#include "grading/json.hpp"

TestResults* custom_grader(const TestCase &tc, const nlohmann::json &j) {


  // ========================================
  // GRAB THE COMMAND LINE ARG
  std::string args = j.value("args","");
  int num = atoi(args.c_str());
  if (!(num > 1)) {
    return new TestResults(0.0,"ERROR! args should be > 1 (specify number of values to sum)");
  }
  

  // ========================================
  // OPEN THE STUDENT OUTPUT FILES
  std::vector<std::string> filenames = stringOrArrayOfStrings(j,"filename");
  std::vector<std::string> contents_of_files;
  for (int i = 0; i < filenames.size(); i++) {
    std::string file_contents;
    std::string f = tc.prefix() + "_" + filenames[i];
    if (!getFileContents(f,file_contents)) {
      return new TestResults(0.0,"ERROR!  Could not open student file: '" + f);
    }    
    contents_of_files.push_back(file_contents);
  }


  // rubric items to check
  bool total_string = true;
  bool correct_total = true;
  bool correct_num = true;
  bool is_random = true;


  // ========================================
  // LOOP OVER ALL FILES
  for (int x = 0; x < contents_of_files.size(); x++) {
    std::stringstream ss(contents_of_files[x]);
    std::string token;
    bool found_ts = false;
    int computed_total = 0;
    int num_values = 0;
    int last_value = 0;
    while (ss >> token) {
      if (token == "total") {
        found_ts = true;
        break;
      }
      last_value = atoi(token.c_str());
      computed_total += last_value;
      num_values++;
    }
    int total;
    if (found_ts) {
      ss >> token;
      if (token != "=") { found_ts = false; }
      if (!(ss >> token)) { found_ts = false; }
      total = atoi(token.c_str());
    }
    if (!found_ts) {
      // if the total string is missing, use the last value as the total
      total = last_value;
      computed_total -= last_value;
      num_values--;
      total_string = false;
    }
    if (total != computed_total) { 
      correct_total = false; 
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
  std::string message;
  float grade = 1.0;

  if (!total_string)  {
    message += "ERROR!  MISSING \"total = \" string "; 
    grade -= 0.1;
  }
  if (!correct_total)  {
    message += "ERROR!  INCORRECT TOTAL ";
    grade -= 0.2;
  }
  if (!correct_num) {
    grade -= 0.3;
    message += "ERROR!  WRONG NUMBER OF VALUES "; 
  }
  if (!is_random) {
    grade -= 0.5;
    message += "ERROR!  NOT RANDOM "; 
  }

  grade = std::max(0.0f,grade);
  return new TestResults(grade,message);
}
