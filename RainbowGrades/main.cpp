#include <iostream>
#include <cassert>
#include <fstream>
#include <sstream>
#include <string>
#include <vector>
#include <sstream>
#include <iomanip>
#include <map>
#include <algorithm>
#include <ctime>
#include <sstream>
#include <cmath>

std::vector<std::vector<std::string> > HACKMAXPROJECTS;


#include "student.h"
#include "iclicker.h"
#include "gradeable.h"
#include "grade.h"
#include "json.hpp"

using nlohmann::json;

// defined in iclicker.cpp
std::string ReadQuoted(std::istream &istr);
void suggest_curves(std::vector<Student*> &students);




//====================================================================
// DIRECTORIES & FILES

std::string ICLICKER_ROSTER_FILE              = "./iclicker_Roster.txt";
std::string OUTPUT_FILE                       = "./output.html";
std::string CUSTOMIZATION_FILE                = "./customization.txt";

std::string RAW_DATA_DIRECTORY                = "./raw_data/";
std::string INDIVIDUAL_FILES_OUTPUT_DIRECTORY = "./individual_summary_html/";
std::string ALL_STUDENTS_OUTPUT_DIRECTORY     = "./all_students_summary_html/";


//====================================================================
// INFO ABOUT GRADING FOR COURSE

std::vector<GRADEABLE_ENUM> ALL_GRADEABLES;

std::map<GRADEABLE_ENUM,Gradeable>  GRADEABLES;

float LATE_DAY_PERCENTAGE_PENALTY = 0;
bool  TEST_IMPROVEMENT_AVERAGING_ADJUSTMENT = false;
bool  LOWEST_TEST_COUNTS_HALF = false;

bool QUIZ_NORMALIZE_AND_DROP_TWO = false;

std::vector<std::string> ICLICKER_QUESTION_NAMES;
float MAX_ICLICKER_TOTAL;

std::map<std::string,float> CUTOFFS;

std::map<Grade,int> grade_counts;
std::map<Grade,float> grade_avg;
int took_final = 0;
int auditors = 0;
int dropped = 0;

Student* PERFECT_STUDENT_POINTER;
Student* AVERAGE_STUDENT_POINTER;

//====================================================================
// INFO ABOUT NUMBER OF SECTIONS

std::map<int,std::string> sectionNames;
std::map<std::string,std::string> sectionColors;

bool validSection(int section) {
  return (sectionNames.find(section) != sectionNames.end());
}


std::string sectionName(int section) {
  std::map<int,std::string>::const_iterator itr = sectionNames.find(section);
  if (itr == sectionNames.end()) 
    return "NONE";
  return itr->second;
}




//====================================================================

char GLOBAL_EXAM_TITLE[MAX_STRING_LENGTH] = "exam title uninitialized";
char GLOBAL_EXAM_DATE[MAX_STRING_LENGTH] = "exam date uninitialized";
char GLOBAL_EXAM_TIME[MAX_STRING_LENGTH] = "exam time uninitialized";
char GLOBAL_EXAM_DEFAULT_ROOM[MAX_STRING_LENGTH] = "exam default room uninitialized";

float GLOBAL_MIN_OVERALL_FOR_ZONE_ASSIGNMENT = 0.1;

int BONUS_WHICH_LECTURE = -1;
std::string BONUS_FILE;

//====================================================================
// INFO ABOUT OUTPUT FORMATTING

bool DISPLAY_INSTRUCTOR_NOTES = false;
bool DISPLAY_EXAM_SEATING = false;
bool DISPLAY_MOSS_DETAILS = false;
bool DISPLAY_FINAL_GRADE = false;
bool DISPLAY_GRADE_SUMMARY = false;
bool DISPLAY_GRADE_DETAILS = false;
bool DISPLAY_ICLICKER = false;

std::vector<std::string> MESSAGES;


//====================================================================

std::ofstream priority_stream("priority.txt");
std::ofstream late_days_stream("late_days.txt");

void PrintExamRoomAndZoneTable(std::ofstream &ostr, Student *s);

//====================================================================





//====================================================================
// sorting routines 


bool by_overall(const Student* s1, const Student* s2) {
  float s1_overall = s1->overall_b4_moss();
  float s2_overall = s2->overall_b4_moss();

  if (s1 == AVERAGE_STUDENT_POINTER) return true;
  if (s2 == AVERAGE_STUDENT_POINTER) return false;
  
  if (s1_overall > s2_overall+0.0001) return true;
  if (fabs (s1_overall - s2_overall) < 0.0001 &&
      s1->getSection() == 0 &&
      s2->getSection() != 0)
    return true;

  return false;
}


bool by_test_and_exam(const Student* s1, const Student* s2) {
  float val1 = s1->GradeablePercent(GRADEABLE_ENUM::TEST) + s1->GradeablePercent(GRADEABLE_ENUM::EXAM);
  float val2 = s2->GradeablePercent(GRADEABLE_ENUM::TEST) + s2->GradeablePercent(GRADEABLE_ENUM::EXAM);
  
  if (val1 > val2) return true;
  if (fabs (val1-val2) < 0.0001 &&
      s1->getSection() == 0 &&
      s2->getSection() != 0)
    return true;
  
  return false;
}




// FOR GRADEABLES
class GradeableSorter {
public:
  GradeableSorter(GRADEABLE_ENUM g) : g_(g) {}
  bool operator()(Student *s1, Student *s2) {
    return (s1->GradeablePercent(g_) > s2->GradeablePercent(g_) ||
            (s1->GradeablePercent(g_) == s2->GradeablePercent(g_) &&
             by_overall(s1,s2)));
  }
private:
  GRADEABLE_ENUM g_;
};


// FOR OTHER THINGS


bool by_name(const Student* s1, const Student* s2) {
  return (s1->getLastName() < s2->getLastName() ||
          (s1->getLastName() == s2->getLastName() &&
           s1->getFirstName() < s2->getFirstName()));
}


bool by_section(const Student *s1, const Student *s2) {
  if (s2->getIndependentStudy() == true && s1->getIndependentStudy() == false) return false;
  if (s2->getIndependentStudy() == false && s1->getIndependentStudy() == true) return false;
  if (s2->getSection() <= 0 && s1->getSection() <= 0) {
    return by_name(s1,s2);
  }
  if (s2->getSection() == 0) return true;
  if (s1->getSection() == 0) return false;
  if (s1->getSection() < s2->getSection()) return true;
  if (s1->getSection() > s2->getSection()) return false;
    return by_name(s1,s2);
}

bool by_iclicker(const Student* s1, const Student* s2) {
  return (s1->getIClickerTotalFromStart() > s2->getIClickerTotalFromStart());
}


// sorting function for letter grades
bool operator< (const Grade &a, const Grade &b) {  
  if (a.value == b.value) return false;

  if (a.value == "A") return true;
  if (b.value == "A") return false;
  if (a.value == "A-") return true;
  if (b.value == "A-") return false;

  if (a.value == "B+") return true;
  if (b.value == "B+") return false;
  if (a.value == "B") return true;
  if (b.value == "B") return false;
  if (a.value == "B-") return true;
  if (b.value == "B-") return false;

  if (a.value == "C+") return true;
  if (b.value == "C+") return false;
  if (a.value == "C") return true;
  if (b.value == "C") return false;
  if (a.value == "C-") return true;
  if (b.value == "C-") return false;

  if (a.value == "D+") return true;
  if (b.value == "D+") return false;
  if (a.value == "D") return true;
  if (b.value == "D") return false;

  if (a.value == "F") return true;
  if (b.value == "F") return false;

  return false;
}

//====================================================================

void gradeable_helper(std::ifstream& istr, GRADEABLE_ENUM g) {
  int c; float p, m;
  istr >> c >> p >> m;
  assert (GRADEABLES.find(g) == GRADEABLES.end());

  Gradeable answer (c,p,m);
  GRADEABLES.insert(std::make_pair(g,answer));
  assert (GRADEABLES[g].getCount() >= 0);
  assert (GRADEABLES[g].getPercent() >= 0.0 && GRADEABLES[g].getPercent() <= 1.0);
  assert (GRADEABLES[g].getMaximum() >= 0.0);
}


bool string_to_gradeable_enum(const std::string &s, GRADEABLE_ENUM &return_value) {
  if (s == "reading")               { return_value = GRADEABLE_ENUM::READING;        return true;  }
  if (s == "exercise")              { return_value = GRADEABLE_ENUM::EXERCISE;       return true;  }
  if (s == "lab" || s == "Lab")     { return_value = GRADEABLE_ENUM::LAB;            return true;  }
  if (s == "participation")         { return_value = GRADEABLE_ENUM::PARTICIPATION;  return true;  }
  if (s == "hw" || s == "homework") { return_value = GRADEABLE_ENUM::HOMEWORK;       return true;  }
  if (s == "project")               { return_value = GRADEABLE_ENUM::PROJECT;        return true;  }
  if (s == "quiz" || s == "quizze") { return_value = GRADEABLE_ENUM::QUIZ;           return true;  }
  if (s == "test")                  { return_value = GRADEABLE_ENUM::TEST;           return true;  }
  if (s == "exam")                  { return_value = GRADEABLE_ENUM::EXAM;           return true;  }
  if (s == "instructor_note")       { return_value = GRADEABLE_ENUM::NOTE;           return true;  }
  return false;
}

//====================================================================


void preprocesscustomizationfile() {
  std::ifstream istr(CUSTOMIZATION_FILE.c_str());
  assert (istr);
  std::string token;
  while (istr >> token) {
    if (token[0] == '#') {
      // comment line!
      char line[MAX_STRING_LENGTH];
      istr.getline(line,MAX_STRING_LENGTH);

    } else if (token.size() > 4 && token.substr(0,4) == "num_") {
      
      GRADEABLE_ENUM g;
      // also take 's' off the end
      bool success = string_to_gradeable_enum(token.substr(4,token.size()-5),g);
      
      if (success) {
        gradeable_helper(istr,g);

        ALL_GRADEABLES.push_back(g);

      } else {
        std::cout << "UNKNOWN GRADEABLE: " << token.substr(4,token.size()-5) << std::endl;
        exit(0);
      }

    } else if (token == "hackmaxprojects") {

      char line[MAX_STRING_LENGTH];
      istr.getline(line,MAX_STRING_LENGTH);
      std::stringstream ss(line);
      std::vector<std::string> items;
      std::string i;
      while (ss >> i) {
        items.push_back(i);
      }
      std::cout << "HACK MAX " << items.size() << std::endl;

      std::cout <<  "   HMP=" << HACKMAXPROJECTS.size() << std::endl;

      HACKMAXPROJECTS.push_back(items);

    } else if (token == "display") {
      istr >> token;

      if (token == "instructor_notes") {
        DISPLAY_INSTRUCTOR_NOTES = true;
      } else if (token == "exam_seating") {
        DISPLAY_EXAM_SEATING = true;
      } else if (token == "moss_details") {
        DISPLAY_MOSS_DETAILS = true;
      } else if (token == "final_grade") {
        DISPLAY_FINAL_GRADE = true;
      } else if (token == "grade_summary") {
        DISPLAY_GRADE_SUMMARY = true;
      } else if (token == "grade_details") {
        DISPLAY_GRADE_DETAILS = true;
      } else if (token == "iclicker") {
        DISPLAY_ICLICKER = true;

      } else {
        std::cout << "OOPS " << token << std::endl;
        exit(0);
      }
      char line[MAX_STRING_LENGTH];
      istr.getline(line,MAX_STRING_LENGTH);
    } else if (token == "use") {
      
      istr >> token;

      if (token == "late_day_penalty") {
        istr >> LATE_DAY_PERCENTAGE_PENALTY;
        assert (LATE_DAY_PERCENTAGE_PENALTY >= 0.0 && LATE_DAY_PERCENTAGE_PENALTY < 0.25);
        char line[MAX_STRING_LENGTH];
        istr.getline(line,MAX_STRING_LENGTH);
      } else if (token == "test_improvement_averaging_adjustment") {
        TEST_IMPROVEMENT_AVERAGING_ADJUSTMENT = true;
      } else if (token == "quiz_normalize_and_drop_two") {
        QUIZ_NORMALIZE_AND_DROP_TWO = true;
      } else if (token == "lowest_test_counts_half") {
        LOWEST_TEST_COUNTS_HALF = true;
      } else {
        std::cout << "ERROR: unknown use " << token << std::endl;
        exit(0);
      }

    } else if (token == "remove_lowest") {
      istr >> token;
      if (token == "HOMEWORK") {
        int num;
        istr >> num;
        assert (num >= 0 && num < GRADEABLES[GRADEABLE_ENUM::HOMEWORK].getCount());
        GRADEABLES[GRADEABLE_ENUM::HOMEWORK].setRemoveLowest(num);
      }
    }
  }
}


void MakeRosterFile(std::vector<Student*> &students) {

  std::sort(students.begin(),students.end(),by_name);

  std::ofstream ostr("./iclicker_Roster.txt");


  for (unsigned int i = 0; i < students.size(); i++) {
    std::string foo = "active";
    if (students[i]->getLastName() == "") continue;
    if (students[i]->getSection() <= 0 || students[i]->getSection() > 10) continue;
    if (students[i]->getGradeableItemGrade(GRADEABLE_ENUM::TEST,0).getValue() < 1) {
      //std::cout << "STUDENT DID NOT TAKE TEST 1  " << students[i]->getUserName() << std::endl;
      foo = "inactive";
    }
    std::string room = students[i]->getExamRoom();
    std::string zone = students[i]->getExamZone();
    if (room == "") room = "DCC 308";
    if (zone == "") zone = "SEE INSTRUCTOR";



#if 0
    ostr 
      << std::left << std::setw(15) << students[i]->getLastName() 
      << std::left << std::setw(13) << students[i]->getFirstName() 
      << std::left << std::setw(12) << students[i]->getUserName()
      << std::left << std::setw(12) << room
      << std::left << std::setw(10) << zone
      << std::endl;

    ostr 
      << students[i]->getLastName() << ","
      << students[i]->getFirstName() << ","
      << students[i]->getUserName() << std::endl;

#else

    ostr 
      << students[i]->getSection()   << "\t"
      << students[i]->getLastName()     << "\t"
      << students[i]->getFirstName() << "\t"
      << students[i]->getUserName()  << "\t"
      //<< foo 
      << std::endl;
 
#endif
  }

}


// defined in zone.cpp
void LoadExamSeatingFile(const std::string &zone_counts_filename, const std::string &zone_assignments_filename, std::vector<Student*> &students);



void processcustomizationfile(std::vector<Student*> &students, bool students_loaded) {

  //  Student *blank    = GetStudent(students,"");
  Student *perfect  = GetStudent(students,"PERFECT");
  Student *student_average  = GetStudent(students,"AVERAGE");
  Student *lowest_a = GetStudent(students,"LOWEST A-");
  Student *lowest_b = GetStudent(students,"LOWEST B-");
  Student *lowest_c = GetStudent(students,"LOWEST C-");
  Student *lowest_d = GetStudent(students,"LOWEST D");

  if (students_loaded == false) {
    preprocesscustomizationfile();
    // blank    = new Student();
    perfect  = new Student();perfect->setUserName("PERFECT");
    student_average = new Student();student_average->setUserName("AVERAGE");

    lowest_a = new Student();lowest_a->setUserName("LOWEST A-");lowest_a->setFirstName("approximate");
    lowest_b = new Student();lowest_b->setUserName("LOWEST B-");lowest_b->setFirstName("approximate");
    lowest_c = new Student();lowest_c->setUserName("LOWEST C-");lowest_c->setFirstName("approximate");
    lowest_d = new Student();lowest_d->setUserName("LOWEST D"); lowest_d->setFirstName("approximate");

    PERFECT_STUDENT_POINTER = perfect;
    AVERAGE_STUDENT_POINTER = student_average;
  } 


  std::ifstream istr(CUSTOMIZATION_FILE.c_str());
  assert (istr);

  std::string token,token2;
  //int num;
  int which;
  std::string which_token;
  float p_score,a_score,b_score,c_score,d_score;

  std::string iclicker_remotes_filename;
  std::vector<std::vector<iClickerQuestion> > iclicker_questions(MAX_LECTURES+1);

  while (istr >> token) {

    //std::cout << "TOKEN " << token << std::endl;

    if (token[0] == '#') {
      // comment line!
      char line[MAX_STRING_LENGTH];
      istr.getline(line,MAX_STRING_LENGTH);
    } else if (token == "section") {
      int section;
      std::string section_name;
      istr >> section >> section_name;
      if (students_loaded == false) {
        std::cout << "MAKE ASSOCIATION " << section << " " << section_name << std::endl;
        assert (!validSection(section)); 
        sectionNames[section] = section_name;
        
        static int counter = 0;
        if (sectionColors.find(section_name) == sectionColors.end()) {
          if (counter == 0) {
            sectionColors[section_name] = "ccffcc"; // lt green
          } else if (counter == 1) {
            sectionColors[section_name] = "ffcccc"; // lt salmon
          } else if (counter == 2) {
            sectionColors[section_name] = "ffffaa"; // lt yellow
          } else if (counter == 3) {
            sectionColors[section_name] = "ccccff"; // lt blue-purple
          } else if (counter == 4) {
            sectionColors[section_name] = "aaffff"; // lt cyan
          } else if (counter == 5) {
            sectionColors[section_name] = "ffaaff"; // lt magenta
          } else if (counter == 6) {
            sectionColors[section_name] = "88ccff"; // blue 
          } else if (counter == 7) {
            sectionColors[section_name] = "cc88ff"; // purple 
          } else if (counter == 8) {
            sectionColors[section_name] = "88ffcc"; // mint 
          } else if (counter == 9) {
            sectionColors[section_name] = "ccff88"; // yellow green
          } else if (counter == 10) {
            sectionColors[section_name] = "ff88cc"; // pink
          } else if (counter == 11) {
            sectionColors[section_name] = "ffcc88"; // orange
          } else if (counter == 12) {
            sectionColors[section_name] = "ffff33"; // yellow
          } else if (counter == 13) {
            sectionColors[section_name] = "ff33ff"; // magenta
          } else if (counter == 14) {
            sectionColors[section_name] = "33ffff"; // cyan
          } else if (counter == 15) {
            sectionColors[section_name] = "6666ff"; // blue-purple
          } else if (counter == 16) {
            sectionColors[section_name] = "66ff66"; // green
          } else if (counter == 17) {
            sectionColors[section_name] = "ff6666"; // red
          } else {
            sectionColors[section_name] = "aaaaaa"; // grey 
          }
          counter++;
        }
      }

    } else if (token == "message") {
      // general message at the top of the file
      char line[MAX_STRING_LENGTH];
      istr.getline(line,MAX_STRING_LENGTH);
      if (students_loaded == false) continue;
      MESSAGES.push_back(line);
    } else if (token == "warning") {
      // EWS early warning system [ per student ]
      std::string username;
      istr >> username;
      char line[MAX_STRING_LENGTH];
      istr.getline(line,MAX_STRING_LENGTH);
      if (students_loaded == false) continue;
      Student *s = GetStudent(students,username);
      if (s == NULL) {
        std::cout << username << std::endl;
      }
      assert (s != NULL);
      s->addWarning(line);
    } else if (token == "recommend") {
      // UTA/mentor recommendations [ per student ]
      std::string username;
      istr >> username;
      char line[MAX_STRING_LENGTH];
      istr.getline(line,MAX_STRING_LENGTH);
      Student *s = GetStudent(students,username);
      if (students_loaded == false) continue;
      assert (s != NULL);
      s->addRecommendation(line);
    } else if (token == "note") {
      // other grading note [ per student ]
      std::string username;
      istr >> username;
      char line[MAX_STRING_LENGTH];
      istr.getline(line,MAX_STRING_LENGTH);
      if (students_loaded == false) continue;
      Student *s = GetStudent(students,username);
      if (s == NULL) {
        std::cout << "USERNAME " << username << std::endl;
      }
      assert (s != NULL);
      s->addNote(line);

    } else if (token == "iclicker_ids") {
      istr >> iclicker_remotes_filename;
    } else if (token == "iclicker") {
      int which_lecture;
      std::string clicker_file;
      int which_column;
      std::string correct_answer;
      istr >> which_lecture >> clicker_file >> which_column >> correct_answer;
      assert (which_lecture >= 1 && which_lecture <= MAX_LECTURES);
      if (students_loaded == false) continue;
      iclicker_questions[which_lecture].push_back(iClickerQuestion(clicker_file,which_column,correct_answer));
    } else if (token == "audit") {
      // other grading note [ per student ]
      std::string username;
      istr >> username;
      if (students_loaded == false) continue;
      Student *s = GetStudent(students,username);
      assert (s != NULL);
      assert (s->getAudit() == false);
      s->setAudit();
      s->addNote("AUDIT");

    } else if (token == "withdraw") {
      // other grading note [ per student ]
      std::string username;
      istr >> username;
      if (students_loaded == false) continue;
      Student *s = GetStudent(students,username);
      assert (s != NULL);
      assert (s->getWithdraw() == false);
      s->setWithdraw();
      s->addNote("LATE WITHDRAW");


    } else if (token == "independentstudy") {
      // other grading note [ per student ]
      std::string username;
      istr >> username;
      if (students_loaded == false) continue;
      Student *s = GetStudent(students,username);
      assert (s != NULL);
      assert (s->getIndependentStudy() == false);
      s->setIndependentStudy();
      s->addNote("INDEPENDENT STUDY");

    } else if (token == "manual_grade") {
      std::string username,grade;
      istr >> username >> grade;
      char line[MAX_STRING_LENGTH];
      istr.getline(line,MAX_STRING_LENGTH);
      if (students_loaded == false) continue;
      Student *s = GetStudent(students,username);
      assert (s != NULL);
      s->ManualGrade(grade,line);
    } else if (token == "moss") {
      std::string username;
      int hw;
      float penalty;
      istr >> username >> hw >> penalty;
      assert (hw >= 1 && hw <= 10);
      assert (penalty >= -0.01 && penalty <= 1.01);
      // ======================================================================
      // MOSS

      if (students_loaded == false) continue;
      Student *s = GetStudent(students,username);
      if (s == NULL) {
        std::cout << "unknown username " << username << std::endl;
      }
      assert (s != NULL);
      s->mossify(hw,penalty);

    } else if (token == "final_cutoff") {

      //      FINAL_GRADE = true;
      std::string grade;
      float cutoff;
      istr >> grade >> cutoff;
      assert (grade == "A" ||
              grade == "A-" ||
              grade == "B+" ||
              grade == "B" ||
              grade == "B-" ||
              grade == "C+" ||
              grade == "C" ||
              grade == "C-" ||
              grade == "D+" ||
              grade == "D");
      CUTOFFS[grade] = cutoff;
    } else if (token.size() > 4 && token.substr(0,4) == "num_") {
      char line[MAX_STRING_LENGTH];
      istr.getline(line,MAX_STRING_LENGTH);
      
    } else if (token == "use") {

      char line[MAX_STRING_LENGTH];
      istr.getline(line,MAX_STRING_LENGTH);
      continue;

    } else if (token == "hackmaxprojects") {

      char line[MAX_STRING_LENGTH];
      istr.getline(line,MAX_STRING_LENGTH);
      continue;


    } else if (token == "display") {

      char line[MAX_STRING_LENGTH];
      istr.getline(line,MAX_STRING_LENGTH);
      continue;


    } else if (token == "exam_title") {
      istr.getline(GLOBAL_EXAM_TITLE,MAX_STRING_LENGTH);
      continue;
    } else if (token == "exam_date") {
      istr.getline(GLOBAL_EXAM_DATE,MAX_STRING_LENGTH);
      continue;
    } else if (token == "exam_time") {
      istr.getline(GLOBAL_EXAM_TIME,MAX_STRING_LENGTH);
      continue;
    } else if (token == "exam_default_room") {
      istr.getline(GLOBAL_EXAM_DEFAULT_ROOM,MAX_STRING_LENGTH);
      continue;
    } else if (token == "min_overall_for_zone_assignment") {
      istr >> GLOBAL_MIN_OVERALL_FOR_ZONE_ASSIGNMENT;
      continue;
    } else if (token == "bonus_latedays") {
      char x[MAX_STRING_LENGTH];
      istr.getline(x,MAX_STRING_LENGTH);
      std::stringstream ssx(x);
      ssx >> BONUS_WHICH_LECTURE >> BONUS_FILE;
      std::cout << "BONUS LATE DAYS" << std::endl;
      continue;
    } else if (token == "exam_seating") {

      //      DISPLAY_EXAM_SEATING = true;

      if (students_loaded == false) {
        char line[MAX_STRING_LENGTH];
        istr.getline(line,MAX_STRING_LENGTH);
        continue;
      } else {

        std::cout << "TOKEN IS EXAM SEATING" << std::endl;
        istr >> token >> token2;
        
        LoadExamSeatingFile(token,token2,students);

        MakeRosterFile(students);
      }

    } else {
      if (students_loaded == true) continue;

      GRADEABLE_ENUM g;
      bool success = string_to_gradeable_enum(token,g);
      
      if (success) {
        
        char gradesline[1000];
        istr.getline(gradesline,1000);
        
        std::stringstream ss(gradesline);


        if (g == GRADEABLE_ENUM::HOMEWORK ||
            g == GRADEABLE_ENUM::LAB ||
            g == GRADEABLE_ENUM::READING ||
            g == GRADEABLE_ENUM::QUIZ ||
            g == GRADEABLE_ENUM::EXAM ||
            g == GRADEABLE_ENUM::PARTICIPATION ||
            g == GRADEABLE_ENUM::PROJECT ||
            g == GRADEABLE_ENUM::TEST ||
            g == GRADEABLE_ENUM::NOTE) {
          ss >> which_token;
          
          assert (!GRADEABLES[g].hasCorrespondence(which_token));
          
          which = GRADEABLES[g].setCorrespondence(which_token);

        } else {
          ss >> which;
        }

        std::cout << gradesline << std::endl;

        if (!(ss >> p_score)) {
          std::cout << "ERROR READING: '" << gradesline << "'" << std::endl;
          std::cout << which_token << " " << p_score << std::endl;
          exit(1);
        }

        a_score = 0.9*p_score;
        b_score = 0.8*p_score;
        c_score = 0.7*p_score;
        d_score = 0.6*p_score;
        
        ss >> a_score >> b_score >> c_score >> d_score;
        
        assert (p_score >= a_score &&
                a_score >= b_score &&
                b_score >= c_score &&
                c_score >= d_score);

        assert (which >= 0 && which < GRADEABLES[g].getCount());
        perfect->setGradeableItemGrade(g,which, p_score);
        lowest_a->setGradeableItemGrade(g,which, a_score);
        lowest_b->setGradeableItemGrade(g,which, b_score);
        lowest_c->setGradeableItemGrade(g,which, c_score);
        lowest_d->setGradeableItemGrade(g,which, d_score);

      } else {
        std::cout << "ERROR: UNKNOWN TOKEN  X" << token << std::endl;
      }

    }
  }
  
  if (students_loaded == false) {
    //    students.push_back(blank);
    students.push_back(perfect);
    students.push_back(student_average);
    students.push_back(lowest_a);
    students.push_back(lowest_b);
    students.push_back(lowest_c);
    students.push_back(lowest_d);
  } else {
    MatchClickerRemotes(students, iclicker_remotes_filename);
    AddClickerScores(students,iclicker_questions);
  }
}



void load_student_grades(std::vector<Student*> &students) {

  Student *perfect = GetStudent(students,"PERFECT");
  assert (perfect != NULL);

  Student *student_average = GetStudent(students,"AVERAGE");
  assert (student_average != NULL);

  
  std::string command2 = "ls -1 " + RAW_DATA_DIRECTORY + "*.json > files_json.txt";

  system(command2.c_str());
  
  std::ifstream files_istr("files_json.txt");
  assert(files_istr);
  std::string filename;
  int count = 0;
  while (files_istr >> filename) {
	std::ifstream istr(filename.c_str());
	assert(istr);
	Student *s = new Student();
	
	count++;
	
	json j;
	j << istr;

	for (json::iterator itr = j.begin(); itr != j.end(); itr++) {
	  std::string token = itr.key();
	  // std::cout << "token: " << token << "!" << std::endl;
	  GRADEABLE_ENUM g;
	  bool gradeable_enum_success = string_to_gradeable_enum(token,g);
      if (!gradeable_enum_success && token != "Other" && token != "rubric" && token != "Test") {
		// non grableables
		if (token == "rcs_id") {
			s->setUserName(j[token].get<std::string>());
		} else if (token == "first_name") {
			s->setFirstName(j[token].get<std::string>());
		} else if (token == "last_name") {
			s->setLastName(j[token].get<std::string>());
		} else if (token == "last_update") {
			s->setLastUpdate(j[token].get<std::string>());
		} else if (token == "section") {
		  int a = j[token].get<int>();
		  if (!validSection(a)) {
		    // the "drop" section is one bigger than the greatest valid section
		    if (!validSection(a-1)){
			  std::cerr << "WARNING: invalid section " << a << std::endl;
		    }
		  }
		  s->setSection(a);
		} else {
            std::cout << "UNKNOWN TOKEN Y '" << token << "'" << std::endl;
			exit(0);
		}
	  } else {
	    for (json::iterator itr2 = (itr.value()).begin(); itr2 != (itr.value()).end(); itr2++) {
		  int which;
		  float value;
		  bool invalid = false;
	      
		  std::string gradeable_id;
		  std::string gradeable_name;
		  std::string other_note;
		  
		  gradeable_id = itr2.key();
		  gradeable_name = (itr2.value())["name"].get<std::string>();
		  value = (itr2.value())["score"].get<float>();
		  
		  if ((itr2.value()).find("text") != (itr2.value()).end()) {
			other_note = (itr2.value())["text"].get<std::string>();
		  }

		  // Search through the gradeable categories as needed to find where this item belongs
		  // (e.g. project may be prefixed by "hw", or exam may be prefixed by "test")
		  for (unsigned int i = 0; i < ALL_GRADEABLES.size(); i++) {
		    GRADEABLE_ENUM g2 = ALL_GRADEABLES[i];
		    if (GRADEABLES[g2].hasCorrespondence(gradeable_id)) {
		      g = g2;
		    }
		  }

		  if (!GRADEABLES[g].hasCorrespondence(gradeable_id)) {
			invalid = true;
			//std::cerr << "ERROR! cannot find a category for this item " << gradeable_id << std::endl;

		  } else {
			invalid = false;
			const std::pair<int,std::string>& c = GRADEABLES[g].getCorrespondence(gradeable_id);
            which = c.first;
			if (c.second == "") {
              GRADEABLES[g].setCorrespondenceName(gradeable_id,gradeable_name); 
            } else {
              assert (c.second == gradeable_name);
            }
		  }
		  
		  if (!invalid) {
			assert (which >= 0);
			assert (value >= 0.0);

                        int ldu = 0;
			if (token == "rubric") {
			  if (j[token][gradeable_id].find("days_late") != j[token][gradeable_id].end()) {
			    if (value <= 0) {
                              std::cout << "Should not be Charged a late day" << std::endl;
			    } else {
                              ldu = j[token][gradeable_id]["days_late"].get<int>();
			    }
			  }
                        }
                        
			s->setGradeableItemGrade(g,which,value,ldu,other_note);
		  }
	    }  
	  }
	}
	students.push_back(s);
  }

}


void start_table_open_file(std::ofstream &ostr, std::string &filename, bool full_details, 
                 const std::vector<Student*> &students, int S, int month, int day, int year,
                 enum GRADEABLE_ENUM which_gradeable_enum);

void start_table_output(std::ofstream &ostr, std::string &filename, bool full_details, 
                 const std::vector<Student*> &students, int S, int month, int day, int year,
                        enum GRADEABLE_ENUM which_gradeable_enum,
                 Student *sp, Student *sa, Student *sb, Student *sc, Student *sd);


void end_table(std::ofstream &ostr,  bool full_details, const std::vector<Student*> &students, int S);



// =============================================================================================
// =============================================================================================
// =============================================================================================



void output_helper(std::vector<Student*> &students,  std::string &sort_order) {

  Student *sp = GetStudent(students,"PERFECT");
  Student *student_average = GetStudent(students,"AVERAGE");
  Student *sa = GetStudent(students,"LOWEST A-");
  Student *sb = GetStudent(students,"LOWEST B-");
  Student *sc = GetStudent(students,"LOWEST C-");
  Student *sd = GetStudent(students,"LOWEST D");
  assert (sp != NULL);
  assert (student_average != NULL);
  assert (sa != NULL);
  assert (sb != NULL);
  assert (sc != NULL);
  assert (sd != NULL);

  // get todays date;
  time_t now = time(0);  
  struct tm * now2 = localtime( & now );
  int month = now2->tm_mon+1;
  int day = now2->tm_mday;
  int year = now2->tm_year+1900;

  std::string command = "rm -f " + OUTPUT_FILE + " " + INDIVIDUAL_FILES_OUTPUT_DIRECTORY + "*html";
  system(command.c_str());

  std::ofstream ostr;

  std::stringstream ss;
  ss << ALL_STUDENTS_OUTPUT_DIRECTORY << "output_" << month << "_" << day << "_" << year << ".html";

  std::cout << "OPEN THIS FILE " << ss.str() << std::endl;
  std::string summary_file = ss.str();

  start_table_open_file(ostr,summary_file,true,students,-1,month,day,year,GRADEABLE_ENUM::NONE);
  start_table_output(ostr,summary_file,true,students,-1,month,day,year,GRADEABLE_ENUM::NONE,
                     sp,sa,sb,sc,sd);

  int next_rank = 1;
  int last_section = -1;

  for (int S = 0; S < (int)students.size(); S++) {
    int rank = next_rank;
    if (students[S] == sp ||
        students[S] == student_average ||
        students[S] == sa ||
        students[S] == sb ||
        students[S] == sc ||
        students[S] == sd ||
        //        students[S]->getUserName() == "" ||
        !validSection(students[S]->getSection())) {
      rank = -1;
    } else {
      if (sort_order == std::string("by_section") &&
          last_section != students[S]->getSection()) {
        last_section = students[S]->getSection();
        next_rank = rank = 1;
      }
      next_rank++;
    }
    Student *this_student = students[S];
    assert (this_student != NULL);
    //output_line(ostr,0,true,this_student,rank,sp,sa,sb,sc,sd,GRADEABLE_ENUM::NONE);
  }
  
  ostr << "</table>\n";
  end_table(ostr,true,students,-1);
  
  command = "cp " + summary_file + " " + OUTPUT_FILE;
  system(command.c_str());
  

  for (int S = 0; S < (int)students.size(); S++) {
    if (students[S]->getSection() == 0) continue;

    std::string file2 = INDIVIDUAL_FILES_OUTPUT_DIRECTORY + students[S]->getUserName() + "_message.html";
    std::ofstream ostr2(file2.c_str());
    if (students[S]->hasPriorityHelpStatus()) {
      ostr2 << "<h3>PRIORITY HELP QUEUE</h3>" << std::endl;
      priority_stream << std::left << std::setw(15) << students[S]->getSection()
                      << std::left << std::setw(15) << students[S]->getUserName() 
                      << std::left << std::setw(15) << students[S]->getFirstName() 
                      << std::left << std::setw(15) << students[S]->getLastName() << std::endl;
      
      
    }
    
    if (MAX_ICLICKER_TOTAL > 0) {
      ostr2 << "<em>recent iclicker = " << students[S]->getIClickerRecent() << " / 12.0</em>" << std::endl;
    }

    PrintExamRoomAndZoneTable(ostr2,students[S]);


    




    int prev = students[S]->getAllowedLateDays(0);

    for (int i = 1; i <= MAX_LECTURES; i++) {
      int tmp = students[S]->getAllowedLateDays(i);
      if (prev != tmp) {

        std::map<int,Date>::iterator itr = LECTURE_DATE_CORRESPONDENCES.find(i);
        if (itr == LECTURE_DATE_CORRESPONDENCES.end()) {
          //std::cout << "NO MATCH FOR LECTURE " << i << std::endl;
          //exit(0);
          continue;
        }
        Date &d = itr->second;
        //late_days_stream << students[S]->getUserName() << " " << i << " " << tmp << std::endl;
        late_days_stream << std::setw(10) << std::left << students[S]->getUserName() << " "

                         << std::setw(12) << std::left << d.getStringRep() << " " 
          
                         << std::setw(2)  << std::right << i  << " " 
                         << std::setw(2)  << std::right << tmp << std::endl;
        prev = tmp;
      }
    }
  }
    
}

    
// =============================================================================================
// =============================================================================================
// =============================================================================================

void load_bonus_late_day(std::vector<Student*> &students, 
                         int which_lecture,
                         std::string bonus_late_day_file) {

  std::ifstream istr(bonus_late_day_file.c_str());
  if (!istr.good()) {
    std::cerr << "ERROR!  could not open " << bonus_late_day_file << std::endl;
    exit(1);
  }

  std::string username;
  while (istr >> username) {
    Student *s = GetStudent(students,username);
    if (s == NULL) {
      std::cerr << "ERROR!  bad username " << username << std::endl;
      exit(1);
    } else {
      s->add_bonus_late_day(which_lecture);
    }
  } 

}


int main(int argc, char* argv[]) {

  std::string sort_order = "by_overall";

  if (argc > 1) {
    assert (argc == 2);
    sort_order = argv[1];
  }

  std::vector<Student*> students;  
  processcustomizationfile(students,false);

  // ======================================================================
  // LOAD ALL THE STUDENT DATA
  load_student_grades(students);

  if (BONUS_FILE != "") {
    load_bonus_late_day(students,BONUS_WHICH_LECTURE,BONUS_FILE);
  }

  // ======================================================================
  // MAKE FAKE STUDENTS FOR THE CURVES
  processcustomizationfile(students,true); 


  // ======================================================================
  // SUGGEST CURVES

  suggest_curves(students);


  // ======================================================================
  // SORT
  std::sort(students.begin(),students.end(),by_overall);


  if (sort_order == std::string("by_overall")) {
    std::sort(students.begin(),students.end(),by_overall);
  } else if (sort_order == std::string("by_name")) {
    std::sort(students.begin(),students.end(),by_name);
  } else if (sort_order == std::string("by_section")) {
    std::sort(students.begin(),students.end(),by_section);
  } else if (sort_order == std::string("by_zone")) {

    DISPLAY_INSTRUCTOR_NOTES = false;
    DISPLAY_EXAM_SEATING = true;
    DISPLAY_MOSS_DETAILS = false;
    DISPLAY_FINAL_GRADE = false;
    DISPLAY_GRADE_SUMMARY = false;
    DISPLAY_GRADE_DETAILS = false;
    DISPLAY_ICLICKER = false;

    std::sort(students.begin(),students.end(),by_name);

  } else if (sort_order == std::string("by_iclicker")) {
    std::sort(students.begin(),students.end(),by_iclicker);

    DISPLAY_ICLICKER = true;

  } else if (sort_order == std::string("by_test_and_exam")) {
    std::sort(students.begin(),students.end(),by_test_and_exam);

  } else {
    assert (sort_order.size() > 3);
    GRADEABLE_ENUM g;
    // take off "by_"
    std::string tmp = sort_order.substr(3,sort_order.size()-3);
    bool success = string_to_gradeable_enum(tmp,g);
    if (success) {
      std::sort(students.begin(),students.end(), GradeableSorter(g) );
    }
    else {
      std::cerr << "UNKNOWN SORT OPTION " << sort_order << std::endl;
      std::cerr << "  Usage: " << argv[0] << " [ by_overall | by_name | by_section | by_zone | by_iclicker | by_lab | by_exercise | by_reading | by_hw | by_test | by_exam | by_test_and_exam ]" << std::endl;
      exit(1);
    }
  }



  // ======================================================================
  // COUNT

  for (unsigned int s = 0; s < students.size(); s++) {

    Student *this_student = students[s];

    if (this_student->getLastName() == "" || this_student->getFirstName() == "") {
      continue;
    }
    if (this_student->getAudit()) {
      auditors++;
      continue;
    }

    Student *sd = GetStudent(students,"LOWEST D");

    if (validSection(this_student->getSection())) {
      std::string student_grade = this_student->grade(false,sd);
      grade_counts[student_grade]++;
      grade_avg[student_grade]+=this_student->overall();
    } else {
      dropped++;
    }
    if (GRADEABLES[GRADEABLE_ENUM::EXAM].getCount() != 0) {
      if (this_student->getGradeableItemGrade(GRADEABLE_ENUM::EXAM,GRADEABLES[GRADEABLE_ENUM::EXAM].getCount()-1).getValue() > 0) {
        took_final++;
      }
    }

  }

  int runningtotal = 0;
  for (std::map<Grade,int>::iterator itr = grade_counts.begin(); 
       itr != grade_counts.end(); itr++) {
    runningtotal += itr->second;

    grade_avg[itr->first] /= float(itr->second);
  }

  // ======================================================================
  // OUTPUT

  output_helper(students,sort_order);

}


void suggest_curves(std::vector<Student*> &students) {

  Student *student_average  = GetStudent(students,"AVERAGE");

  for (unsigned int i = 0; i < ALL_GRADEABLES.size(); i++) {
    GRADEABLE_ENUM g = ALL_GRADEABLES[i];

    for (int i = 0; i < GRADEABLES[g].getCount(); i++) {
      
      std::string gradeable_id = GRADEABLES[g].getID(i);
      if (gradeable_id == "") continue;
      
      const std::string& gradeable_name = GRADEABLES[g].getCorrespondence(gradeable_id).second;
      
      std::cout << gradeable_to_string(g) << " " << gradeable_id << " " << gradeable_name/* << " statistics & suggested curve"*/ << std::endl;
      std::vector<float> scores;
      
      std::map<int, int> section_counts;
      
      for (unsigned int S = 0; S < students.size(); S++) {
        if (students[S]->getSection() > 0 && students[S]->getGradeableItemGrade(g,i).getValue() > 0) {
          scores.push_back(students[S]->getGradeableItemGrade(g,i).getValue());
          section_counts[students[S]->getSection()]++;
        }
      }
      if (scores.size() > 0) {
        //std::cout << "   " << scores.size() << " submitted" << std::endl;
        std::sort(scores.begin(),scores.end());
        float sum = 0;
        for (unsigned int i = 0; i < scores.size(); i++) {
          sum+=scores[i];
        }
        float average = sum / float(scores.size());
        std::cout << "    average=" << std::setprecision(2) << std::fixed << average;

        student_average->setGradeableItemGrade(g,i,average);

        sum = 0;
        for (unsigned int i = 0; i < scores.size(); i++) {
          sum+=(average-scores[i])*(average-scores[i]);
        }
        float stddev = sqrt(sum/float(scores.size()));
        std::cout << "    stddev=" << std::setprecision(2) << std::fixed << stddev;

        std::cout << "    suggested curve:";
        
        std::cout << "    A- cutoff=" << scores[int(0.70*scores.size())];
        std::cout << "    B- cutoff=" << scores[int(0.45*scores.size())];
        std::cout << "    C- cutoff=" << scores[int(0.20*scores.size())];
        std::cout << "    D  cutoff=" << scores[int(0.10*scores.size())];
        std::cout << std::endl;
      }
      
      
      int total = 0;
      std::cout << "   ";
      for (std::map<int,int>::iterator itr = section_counts.begin(); itr != section_counts.end(); itr++) {
        std::cout << " sec#" << itr->first << "=" << itr->second << "  ";
        total += itr->second;
      }
      std::cout << "  TOTAL = " << total << std::endl;
      
      
    }
  }
}

// =============================================================================================
// =============================================================================================
// =============================================================================================

