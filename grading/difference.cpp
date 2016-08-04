/* FILENAME: difference.cpp
 * YEAR: 2014
 * AUTHORS: Please refer to 'AUTHORS.md' for a list of contributors
 * LICENSE: Please refer to 'LICENSE.md' for the conditions of using this code
 *
 * RELEVANT DOCUMENTATION: 
 * Extends the printJSON method to format results into JSON format
 */

#include "difference.h"
#include "json.hpp"

/* METHOD: printJSON
 * ARGS: ostream
 * RETURN: void
 * PURPOSE: print out the data in JSON format for student's result and expected result
 */


// FIXME: Thus function has terrible variable names (diff1, diff2, a, b) 
//   and the code is insufficiently commented for long term maintenance.


void Difference::printJSON(std::ostream & file_out) {
  std::string diff1_name;
  std::string diff2_name;
  
  switch (type) {
    // ByLineByChar;
    // ByWordByChar;
    // VectorVectorStringType;
    // ByLineByWord;
    // VectorOtherType;
    
  case ByLineByChar:
    diff1_name = "line";
    diff2_name = "char";
    break;
  case ByWordByChar:
    diff1_name = "word";
    diff2_name = "char";
    break;
  case ByLineByWord:
    diff1_name = "line";
    diff2_name = "word";
    break;
  default:
    diff1_name = "line";
    diff2_name = "char";
    break;
  }

  nlohmann::json whole_file;
  
  // always have a "differences" tag, even if it is an empty array
  whole_file["differences"] = nlohmann::json::array();

  for (unsigned int a = 0; a < changes.size(); a++) {
    nlohmann::json blob;
    nlohmann::json student;
    nlohmann::json instructor;

    student["start"] = changes[a].a_start;
    for (unsigned int b = 0; b < changes[a].a_changes.size(); b++) {
      nlohmann::json d1;
      d1[diff1_name+"_number"] = changes[a].a_changes[b];
      if (changes[a].a_characters.size() >= b && 
          changes[a].a_characters.size() > 0 &&
          changes[a].a_characters[b].size() > 0) {
        nlohmann::json d2;
        for (unsigned int c=0; c< changes[a].a_characters[b].size(); c++) {
          d2.push_back(changes[a].a_characters[b][c]);
        }
        d1[diff2_name+"_number"] = d2;
      }
      student[diff1_name].push_back(d1);
    }

    instructor["start"] = changes[a].b_start;
    for (unsigned int b = 0; b < changes[a].b_changes.size(); b++) {
      nlohmann::json d1;
      d1[diff1_name+"_number"] = changes[a].b_changes[b];
      if (changes[a].b_characters.size() >= b && 
          changes[a].b_characters.size() > 0 &&
          changes[a].b_characters[b].size() > 0) {
        nlohmann::json d2;
        for (unsigned int c=0; c< changes[a].b_characters[b].size(); c++) {
          d2.push_back(changes[a].b_characters[b][c]);
        }
        d1[diff2_name+"_number"] = d2;
      }
      instructor[diff1_name].push_back(d1);
    }
    
    blob["student"] = student;
    blob["instructor"] = instructor;
    whole_file["differences"].push_back(blob);
  }

  file_out << whole_file.dump(4) << std::endl;
  return;
}

