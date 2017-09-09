#ifndef _ICLICKER_H_
#define _ICLICKER_H_

#include <iostream>
#include <sstream>
#include <string>
#include <vector>
#include <iomanip>


#define MAX_LECTURES 28

extern std::vector<float> GLOBAL_earned_late_days;

class Student;

class Date {
public:
  int year;
  int month;
  int day;

  std::string getStringRep() {
    std::stringstream ss;
    ss << std::setw(2)  << std::setfill('0') << month 
       << "/"
       << std::setw(2)  << std::setfill('0') << day
       << "/"
       << std::setw(4)  << year;
    return ss.str();
  }
};


enum iclicker_answer_enum { ICLICKER_NOANSWER, ICLICKER_INCORRECT, ICLICKER_PARTICIPATED, ICLICKER_CORRECT };

extern std::vector<std::string> ICLICKER_QUESTION_NAMES;

extern std::map<int,Date> LECTURE_DATE_CORRESPONDENCES;

// ==========================================================

class iClickerQuestion {
public:
  iClickerQuestion(const std::string& f, int q, const std::string& ca) {
    handleXMLFilename(f);
    which_question = q;
    assert (which_question >= 0 && which_question < 20);
    correct_answer = ca;
  }

  const std::string& getFilename() const { return filename; }
  int getColumn() const { return 4 + (which_question-1)*6; }
  bool participationQuestion() const { return correct_answer == "ABCDE"; }
  bool isCorrectAnswer(char c) { return correct_answer.find(c) != std::string::npos; }
  void handleXMLFilename(const std::string& f);

private:
  std::string filename;
  int which_question;
  std::string correct_answer;
};

// ==========================================================

void MatchClickerRemotes(std::vector<Student*> &students, const std::string &remotes_filename);
void AddClickerScores(std::vector<Student*> &students, std::vector<std::vector<iClickerQuestion> > iclicker_questions);

#endif
