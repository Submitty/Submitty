#ifndef __testResults__
#define __testResults__

#include <cassert>
#include <iostream>
#include <fstream>
#include <string>
#include <vector>

class TestResults {
public:

  // CONSTRUCTOR
  TestResults(float g=0.0, 
              const std::vector<std::string> &m = {}, 
              const std::string &sd="") :
    my_grade(g),messages(m),swap_difference(sd),distance(0) {}
  
  virtual void printJSON(std::ostream & file_out) {
    if (swap_difference != "") {
      file_out << swap_difference << std::endl;
    } else {
      file_out << "{" << std::endl;
      file_out << "}" << std::endl;
    }
  }

  // ACCESSORS
  float getGrade() { assert (my_grade >= 0); return my_grade; } 
  std::vector<std::string> getMessages() { return messages; }

  // MODIFIERS
  void setGrade(float g) { assert (g >= 0); my_grade = g; }
  void addMessage(const std::string &m) { messages.push_back(m); }


public:
  std::string swap_difference;
  int distance;

protected:

  // REPRESENTATION


  std::vector<std::string> messages;
  float my_grade;
};

#endif
