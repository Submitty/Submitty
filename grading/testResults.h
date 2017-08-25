#ifndef __testResults__
#define __testResults__

#include <cassert>
#include <iostream>
#include <fstream>
#include <string>
#include <vector>
#include <utility>
#include <string.h>
#include <sstream>

#define TEST_RESULT_DIFF_SIZE 1000000
#define TEST_RESULT_MESSAGES_SIZE 10000

#define TEST_RESULT_NUM_MESSAGES 10


enum TEST_RESULTS_MESSAGE_TYPE { MESSAGE_NONE, MESSAGE_FAILURE, MESSAGE_WARNING, MESSAGE_SUCCESS, MESSAGE_INFORMATION };


// ===================================================================================
// ===================================================================================
// helper class needed to allow validation to use shared memory
//
class TestResultsFixedSize {
public:

  void initialize() {
    distance = 0;
    grade = 0;
    tr_error = false;
    tr_warning = false;
    strcpy(diff,"");
    char default_message[] = "ERROR: TestResults not initialized. Probably caused by error in validation. If you cannot debug the issue, ask your instructor to check results_log_validator.txt";
    types[0] = MESSAGE_FAILURE;
    assert (strlen(default_message) < TEST_RESULT_MESSAGES_SIZE-1);
    strcpy(messages,default_message);
  }

  void PACK(std::string d, int dist, std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> > m, float g, bool tr_e, bool tr_w) {
    grade = g;
    distance = dist;
    tr_error = tr_e;
    tr_warning = tr_w;
    if (d.size() >= TEST_RESULT_DIFF_SIZE-1) {
      m.push_back(std::make_pair(MESSAGE_FAILURE,"ERROR: diff too large to calculate/display."));
      strcpy(diff,"");
    } else {
      assert (d.size() < TEST_RESULT_DIFF_SIZE-1);
      strcpy(diff,d.c_str());
    }
    assert (m.size() <= TEST_RESULT_NUM_MESSAGES);
    for (unsigned int i = 0; i < m.size(); i++) {
      if (m[i].second.size() >= TEST_RESULT_MESSAGES_SIZE-1) {
        char default_message[] = "ERROR: messages too large to display.";
        assert (strlen(default_message) < TEST_RESULT_MESSAGES_SIZE-1);
        strcpy(messages+i*TEST_RESULT_MESSAGES_SIZE,default_message);
      } else {
        assert (m[i].second.size() < TEST_RESULT_MESSAGES_SIZE-1);
        strcpy(messages+i*TEST_RESULT_MESSAGES_SIZE,m[i].second.c_str());
      }
      types[i] = m[i].first;
    }
    for (unsigned int i = m.size(); i < TEST_RESULT_NUM_MESSAGES; i++) {
      strcpy(messages+i*TEST_RESULT_MESSAGES_SIZE,"");
      types[i] = MESSAGE_NONE;
    }
  }
  virtual void printJSON(std::ostream & file_out) {
    if (strcmp(diff,"") != 0) {
      file_out << diff << std::endl;
    } else {
      file_out << "{" << std::endl;
      file_out << "}" << std::endl;
    }
  }
  float getGrade() const { return grade; }
  const std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> > getMessages() const { 
    std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> > answer;
    for (int i = 0; i < TEST_RESULT_NUM_MESSAGES; i++) {
      if (types[i] != MESSAGE_NONE) {
        std::string msg = messages+i*TEST_RESULT_MESSAGES_SIZE;
        answer.push_back(std::make_pair(types[i],msg));
      }
    }
    return answer;
  }

  bool hasError() const { return tr_error; }
  bool hasWarning() const { return tr_warning; }

  friend std::ostream& operator<< (std::ostream& ostr, const TestResultsFixedSize &tr) {
    ostr << "TR grade=" << tr.grade << " messages='" << tr.messages << "'";
    return ostr;
  }

private:
  int distance;
  float grade;
  char diff[TEST_RESULT_DIFF_SIZE];
  char messages[TEST_RESULT_NUM_MESSAGES*TEST_RESULT_MESSAGES_SIZE];
  TEST_RESULTS_MESSAGE_TYPE types[TEST_RESULT_NUM_MESSAGES];
  bool tr_error;
  bool tr_warning;
};

// ==============================================================================================

class TestResults {
public:

  // CONSTRUCTOR
  TestResults(float g=0.0, 
              const std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> > &m = {},
              const std::string &sd="") :
    my_grade(g),swap_difference(sd),distance(0),tr_error(false),tr_warning(false) {
    for (int i= 0; i < m.size(); i++) {
      if (m[i].second != "")
        messages.push_back(m[i]);
      if (m[i].first == MESSAGE_FAILURE)
        tr_error = true;
      if (m[i].first == MESSAGE_WARNING)
        tr_warning = true;
    }
  }
  
  virtual void printJSON(std::ostream & file_out) {
    if (swap_difference != "") {
      file_out << swap_difference << std::endl;
    } else {
      file_out << "{" << std::endl;
      file_out << "}" << std::endl;
    }
  }

  // ACCESSORS
  float getGrade() const { assert (my_grade >= 0); return my_grade; }
  const std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> >& getMessages() const { return messages; }
  int getDistance() const { return distance; }
  bool hasError() const { return tr_error; }
  bool hasWarning() const { return tr_warning; }

  // MODIFIERS
  void setGrade(float g) { assert (g >= 0); my_grade = g; }
  void setDistance(int d) { distance = d; }

  void PACK(TestResultsFixedSize* ptr) {
    std::string myself;
    std::stringstream tmp;
    printJSON(tmp);
    myself = tmp.str();
    ptr->PACK(myself,distance,messages,my_grade,tr_error,tr_warning);
  }

protected:

  // REPRESENTATION
  std::vector<std::pair<TEST_RESULTS_MESSAGE_TYPE, std::string> > messages;
  float my_grade;
  std::string swap_difference;
  int distance;
  bool tr_warning;
  bool tr_error;

};

// ===================================================================================

#endif
