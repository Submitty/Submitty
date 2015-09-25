#include <fstream>
#include <sstream>
#include <map>
#include <cassert>

#include "iclicker.h"
#include "student.h"

extern std::vector<std::string> ICLICKER_QUESTION_NAMES;
extern float MAX_ICLICKER_TOTAL;

Student* GetStudent(const std::vector<Student*> &students, const std::string& name);

std::string ReadQuoted(std::istream &istr) {
  char c;
  std::string answer;
  bool success = true;
  istr >> c;
  if (!success || c != '"') {
    //std::cout << success << " OOPS not quote '" << c << "'" << std::endl;
  }

  while (istr >> c) {
    if (c == '"') break;
    answer.push_back(c);
  }
  //std::cout << "FOUND " << answer <<std::endl;
  return answer;
}

std::map<std::string, std::string> GLOBAL_CLICKER_MAP;


void MatchClickerRemotes(std::vector<Student*> &students, const std::string &remotes_filename) {
  if (remotes_filename == "") return;
  std::cout << "READING CLICKER REMOTES FILE: " << remotes_filename << std::endl;

  std::ifstream istr(remotes_filename.c_str());
  if (!istr) return; 

  char c;
  while (1) {
    std::string remote = ReadQuoted(istr);
    bool success = true;
    istr >> c;
    if (!success || c != ',') {
      //std::cout << success << " OOPS-not comma '" << c << "'" << std::endl;
    }
    std::string username = ReadQuoted(istr);
    if (remote == "" || username == "") break;
    //std::cout << "tokens: " << remote << " " << username << std::endl;

    Student *s = GetStudent(students,username);
    if (s == NULL) {
      std::cout << "BAD USERNAME FOR CLICKER MATCHING " << username << std::endl;
      continue;
    }
    assert (s != NULL);
    s->setRemoteID(remote);

    assert (GLOBAL_CLICKER_MAP.find(remote) == GLOBAL_CLICKER_MAP.end());
    GLOBAL_CLICKER_MAP[remote] = username;
  }
}


std::string getItem(const std::string &line, int which) {
  int comma_before = 0;
  for (int i = 0; i < which; i++) {
    comma_before = line.find(',',comma_before)+1;
    assert (comma_before != std::string::npos);
  }
  int comma_after = line.find(',',comma_before);
  return line.substr(comma_before,comma_after-comma_before);
}


void AddClickerScores(std::vector<Student*> &students, std::vector<std::vector<iClickerQuestion> > iclicker_questions) {

  for (int which_lecture = 0; which_lecture < iclicker_questions.size(); which_lecture++) {
    std::vector<iClickerQuestion>& lecture = iclicker_questions[which_lecture];
    for (int which_question = 0; which_question < lecture.size(); which_question++) {
      iClickerQuestion& question = lecture[which_question];

      std::stringstream ss;
      ss << which_lecture << "." << which_question+1;

      ICLICKER_QUESTION_NAMES.push_back(ss.str());
MAX_ICLICKER_TOTAL += 1.0;

      std::ifstream istr(question.getFilename());
      std::cout << question.getFilename();// << std::endl;

      assert (istr);
      char line_helper[5000];
      // ignore first 5 lines
      for (int i = 0; i < 5; i++) {
        istr.getline(line_helper,5000);
      }

      while (istr.getline(line_helper,5000)) {
        std::string line = line_helper;
        std::string remoteid = getItem(line,0);
        std::string item = getItem(line,question.getColumn()-1);
        bool participate = (item != "");
        
        if (!participate) continue;
        //std::cout << "ITEM " << item << " " << item.size() << std::endl;
        assert (item.size() == 1);

        bool correct = question.participationQuestion() || question.isCorrectAnswer(item[0]);
        //std::cout << "ITEM " << remoteid << " " << item << "  " << participate << " " << correct << std::endl;
        std::map<std::string,std::string>::iterator itr = GLOBAL_CLICKER_MAP.find(remoteid);
        if (itr == GLOBAL_CLICKER_MAP.end()) {
          //std::cout << "UNKNOWN CLICKER: " << remoteid << "  " << std::endl;
          std::cout << " " << remoteid;
          continue;
        }
        assert (itr != GLOBAL_CLICKER_MAP.end());
        std::string username = itr->second;
        Student *s = GetStudent(students,username);
        assert (s != NULL);

iclicker_answer_enum grade = ICLICKER_NOANSWER;
        if (question.participationQuestion()) 
          grade = ICLICKER_PARTICIPATED;
        else {
          if (question.isCorrectAnswer(item[0])) {
            grade = ICLICKER_CORRECT;
          } else {
            grade = ICLICKER_INCORRECT;
          }
        }

        s->addIClickerAnswer(ss.str(),item[0],grade);
        //s.seti

      }
      std::cout << std::endl;
    }
  }
}
