#ifndef _ICLICKER_H_
#define _ICLICKER_H_

#include <iostream>
#include <string>
#include <vector>


class Student;


enum iclicker_answer_enum { ICLICKER_NOANSWER, ICLICKER_INCORRECT, ICLICKER_PARTICIPATED, ICLICKER_CORRECT };

extern std::vector<std::string> ICLICKER_QUESTION_NAMES;

// ==========================================================

class iClickerQuestion {
public:
  iClickerQuestion(const std::string& f, int c, const std::string& ca) {
    filename = f;
    column = c;
    correct_answer = ca;
  }

  const std::string& getFilename() const { return filename; }
  int getColumn() const { return column; }
  bool participationQuestion() const { return correct_answer == "ABCDE"; }
  bool isCorrectAnswer(char c) { return correct_answer.find(c) != std::string::npos; }

private:
  std::string filename;
  int column;
  std::string correct_answer;
};

// ==========================================================

void MatchClickerRemotes(std::vector<Student*> &students, const std::string &remotes_filename);
void AddClickerScores(std::vector<Student*> &students, std::vector<std::vector<iClickerQuestion> > iclicker_questions);

#endif
