/* FILENAME: testResults.h
 * YEAR: 2014
 * AUTHORS: Please refer to 'AUTHORS.md' for a list of contributors
 * LICENSE: Please refer to 'LICENSE.md' for the conditions of using this code
 *
 * RELEVANT DOCUMENTATION: 
 * The testResults.h acts as a super class for several other classes such as
 * difference.h and tokens.h.
 */

#ifndef __differences__testResults__
#define __differences__testResults__

#include <cassert>
#include <iostream>
#include <fstream>
#include <string>
#include <vector>

class TestResults {
public:

  TestResults(float g, const std::vector<std::string> &m, const std::string &sd="") { my_grade = g; messages = m; swap_difference=sd; distance=0; }
  TestResults(float g=0.0, const std::string &m="", const std::string &sd="") { my_grade = g; if (m != "") messages.push_back(m); swap_difference=sd; distance=0; }

  virtual ~TestResults() {}
  
  int distance;

  virtual void printJSON(std::ostream & file_out); 

  /* GRADE METHODS */
  /* METHOD: getGrade
   * ARGS: none
   * RETURN: float
   * PURPOSE: Returns a floating point number representing grade
   */
  float getGrade() { assert (my_grade >= 0); return my_grade; } 

  /* METHOD: setGrade
   * ARGS: g - new grade to be set
   * RETURN: void
   * PURPOSE: Sets the current grade to a new grade passed in
   */
  void setGrade(float g) { assert (g >= 0); my_grade = g; }

  /* MESSAGE METHODS */
  /* METHOD: get_message
   * ARGS: none
   * RETURN: string
   * PURPOSE: Returns a string containing the message for the test
   */
  std::vector<std::string> getMessages() { return messages; }

  void addMessage(const std::string &m) { messages.push_back(m); }


protected:
  std::string swap_difference;

  std::vector<std::string> messages;
  float my_grade;
};


/* METHOD: printJSON
 * ARGS: ostream
 * RETURN: void
 * PURPOSE: Prints to a file on the server with the data of the results
 * Extended in difference.cpp
 */
inline void TestResults::printJSON(std::ostream & file_out) {
  if (swap_difference != "") {
    file_out << swap_difference << std::endl;
  } else {
    file_out << "{" << std::endl;
    file_out << "}" << std::endl;
  }
  return;
}

#endif
