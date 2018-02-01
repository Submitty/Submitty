#include <fstream>
#include <sstream>
#include <map>
#include <cassert>

#include "iclicker.h"
#include "student.h"

extern std::vector<std::string> ICLICKER_QUESTION_NAMES;
extern float MAX_ICLICKER_TOTAL;

std::vector<float> GLOBAL_earned_late_days;

std::map<int,Date> LECTURE_DATE_CORRESPONDENCES;

Student* GetStudent(const std::vector<Student*> &students, const std::string& name);

//Internally change .xml paths to .csv paths
void iClickerQuestion::handleXMLFilename(const std::string& f){
  filename = f;
  if(filename.substr(filename.size()-4) == ".xml"){
    filename.resize(filename.size()-3);
    filename += "csv";
  }
}

Date dateFromFilename(const std::string& filename_with_directory) {
  
  std::string filename = filename_with_directory;
  while (true) {
    std::string::size_type pos = filename.find('/');
    if (pos == std::string::npos) break;
    filename = filename.substr(pos+1,filename.size()-pos-1);
  }

  assert (filename.size() == 15);
  assert (filename[0] == 'L');
  //  assert (filename[7] == '_');
  assert (filename.substr(11,4) == ".csv");

  Date answer;

  answer.year  = 2000 + atoi(filename.substr(1,2).c_str());
  answer.month = atoi(filename.substr(3,2).c_str());
  answer.day   = atoi(filename.substr(5,2).c_str());

  //std::cout << "YEAR " << answer.year << std::endl;
  //std::cout << "MONTH " << answer.month << std::endl;
  //std::cout << "DAY " << answer.day << std::endl;

  assert (answer.month >= 1 && answer.month <= 12);
  assert (answer.day >= 1 && answer.day <= 31);

  return answer;
}


std::string ReadRemote(std::istream &istr) {
  char c;
  std::string answer;
  if (!istr.good()) return "";
  istr >> c;
  if (!istr.good()) return "";
  if (c != '#') return answer;
  answer = "#";
  while (1) {
    istr >> c;
    if (c == ',') break;
    answer.push_back(c);
  }
  if (answer == "#T24RLR15") {
    std::cerr << "ERROR!  " << answer << " is not a valid remote ID, this is the clicker model #" << std::endl;
    return "";
  }
  return answer;
}

std::string ReadQuoted(std::istream &istr) {
  char c;
  std::string answer;
  //bool success = true;
  if (  !(istr >> c) || c != '"') {
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

  while (1) {
    std::string remote = ReadRemote(istr);
    std::string username = ReadQuoted(istr);
    if (username == "") break;
    if (remote == "") {
      std::cerr << "ERROR! blank remoteid for " << username << std::endl;
      continue;
    }
    //std::cout << "tokens: " << remote << " " << username << std::endl;
    Student *s = GetStudent(students,username);
    if (s == NULL) {
      std::cout << "BAD USERNAME FOR CLICKER MATCHING " << username << std::endl;
      exit(0);
      continue;
    }
    assert (s != NULL);
    if (s->getRemoteID().size() != 0) {
      std::cout << "student " << username << " has multiple remotes (replacing a lost remote)" << std::endl;
    }
    s->setRemoteID(remote);
    //std::cout << "MATCH " << username << " " << remote << std::endl;
    if (GLOBAL_CLICKER_MAP.find(remote) != GLOBAL_CLICKER_MAP.end()) {
      std::cout << "ERROR!  already have this clicker assigned " << remote << " " << s->getUserName() << std::endl;
    }
    assert (GLOBAL_CLICKER_MAP.find(remote) == GLOBAL_CLICKER_MAP.end());
    GLOBAL_CLICKER_MAP[remote] = username;
  }
}


std::string getItem(const std::string &line, int which) {
  std::string::size_type comma_before = 0;
  for (int i = 0; i < which; i++) {
    comma_before = line.find(',',comma_before)+1;
    assert (comma_before != std::string::npos);
  }
  int comma_after = line.find(',',comma_before);
  return line.substr(comma_before,comma_after-comma_before);
}


void AddClickerScores(std::vector<Student*> &students, std::vector<std::vector<std::vector<iClickerQuestion> > > iclicker_questions) {

  for (unsigned int which_lecture = 0; which_lecture < iclicker_questions.size(); which_lecture++) {
    //std::cout << "which lecture = " << which_lecture << std::endl;
    std::vector<std::vector<iClickerQuestion> >& lecture = iclicker_questions[which_lecture];
    //for (unsigned int which_question = 0; which_question < lecture.size(); which_question++) {
    for (std::size_t which_question = 0; which_question < lecture.size(); which_question++){
      //unsigned int which_question = it->first;
      //iClickerQuestion& question = lecture[which_question];

      std::stringstream ss;
      ss << which_lecture << "." << which_question+1;

      for(unsigned int i=0; i<lecture[which_question].size(); i++) {
        iClickerQuestion &question = lecture[which_question][i];

        if(i==0) {
          ICLICKER_QUESTION_NAMES.push_back(ss.str());
          MAX_ICLICKER_TOTAL += 1.0;
        }


        std::ifstream istr(question.getFilename());
        if (!istr.good()) {
          std::cerr << "ERROR: cannot open file: " << question.getFilename() << std::endl;
        }

        if (LECTURE_DATE_CORRESPONDENCES.find(which_lecture) ==
            LECTURE_DATE_CORRESPONDENCES.end()) {
          Date date = dateFromFilename(question.getFilename());
          LECTURE_DATE_CORRESPONDENCES[which_lecture] = date;
        }


        char line_helper[5000];
        while (istr.getline(line_helper, 5000)) {

          std::string line = line_helper;

          // ignore lines that don't begin with a clicker id
          // all clicker id's start with '#'
          if (line[0] != '#') continue;

          //std::cout << "ITEM " << item << " " << item.size() << std::endl;
          if (item.size() != 1) {
            std::cout << "iclicker " << question.getFilename() << " " << question.getColumn() << std::endl;
          }
          assert (item.size() == 1);

          std::string remoteid = getItem(line, 0);
          std::string item = getItem(line, question.getColumn() - 1);
          bool participate = (item != "");

          if (!participate) continue;

          //std::cout << "ITEM " << item << " " << item.size() << std::endl;

          assert(item.size() == 1);

          //bool correct = question.participationQuestion() || question.isCorrectAnswer(item[0]);

          //std::cout << "ITEM " << remoteid << " " << item << "  " << participate << " " << correct << std::endl;
          std::map<std::string, std::string>::iterator itr = GLOBAL_CLICKER_MAP.find(remoteid);
          if (itr == GLOBAL_CLICKER_MAP.end()) {
            //std::cout << "UNKNOWN CLICKER: " << remoteid << "  " << std::endl;
            std::cout << " " << remoteid;
            continue;
          }
          assert(itr != GLOBAL_CLICKER_MAP.end());
          std::string username = itr->second;
          Student *s = GetStudent(students, username);
          assert(s != NULL);

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

          s->addIClickerAnswer(ss.str(), item[0], grade);
          //s.seti

        }
        std::cout << std::endl;
      }
    }
  }
}
