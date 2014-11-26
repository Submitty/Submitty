/*Copyright (c) 2014, Chris Berger, Jesse Freitas, Severin Ibarluzea,
 Kiana McNellis, Kienan Knight-Boehm

 All rights reserved.
 This code is licensed using the BSD "3-Clause" license. Please refer to
 "LICENSE.md" for the full license
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
