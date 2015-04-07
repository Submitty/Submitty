/* FILENAME: testResults.h
 * YEAR: 2014
 * AUTHORS: Please refer to 'AUTHORS.md' for a list of contributors
 * LICENSE: Please refer to 'LICENSE.md' for the conditions of using this code
 *
 * RELEVANT DOCUMENTATION:
 */

#ifndef __differences__testResults__
#define __differences__testResults__

#include <cassert>
#include <iostream>
#include <fstream>

class TestResults {
public:

  TestResults(float g=-1, const std::string &m="") { my_grade = g; message = m; distance=0; }

  virtual ~TestResults() {}
  
  int distance;

  virtual void printJSON(std::ostream & file_out); // =0;
  //  virtual float grade() { return my_grade; } //=0;
  float getGrade() { assert (my_grade >= 0); return my_grade; } //=0;

  void setGrade(float g) { assert (g >= 0); my_grade = g; }

  std::string get_message() { return message; }
  void setMessage(const std::string &m) { message=m; }
protected:
  std::string message;
  float my_grade;
};

//TestResults::TestResults():distance(0){}

inline void TestResults::printJSON(std::ostream & file_out) {

  file_out << "{" << std::endl;
  file_out << "}" << std::endl;
  return;
}

#endif
