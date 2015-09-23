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

#include "student.h"
#include "iclicker.h"
#include "gradeable.h"
#include "grade.h"



#define MAX_LECTURES 28


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

std::map<GRADEABLE_ENUM,int>   GRADEABLES_COUNT;
std::map<GRADEABLE_ENUM,float> GRADEABLES_FIRST;
std::map<GRADEABLE_ENUM,float> GRADEABLES_PERCENT;
std::map<GRADEABLE_ENUM,float> GRADEABLES_MAXIMUM;
std::map<GRADEABLE_ENUM,int>   GRADEABLES_REMOVE_LOWEST;

float LATE_DAY_PERCENTAGE_PENALTY = 0;
bool  TEST_IMPROVEMENT_AVERAGING_ADJUSTMENT = false;
bool  LOWEST_TEST_COUNTS_HALF = false;

std::vector<std::string> ICLICKER_QUESTION_NAMES;
float MAX_ICLICKER_TOTAL;

std::map<std::string,float> CUTOFFS;

std::map<Grade,int> grade_counts;
std::map<Grade,float> grade_avg;
int took_final = 0;
int auditors = 0;

Student* PERFECT_STUDENT_POINTER;

//====================================================================
// INFO ABOUT NUMBER OF SECTIONS

int MAX_SECTION = 20;
std::map<int,std::string> sectionNames;
std::map<std::string,std::string> sectionColors;

bool validSection(int section) {
  std::cout << "valid section " << section << " " <<   (sectionNames.find(section) != sectionNames.end()) << std::endl;
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

//====================================================================
// INFO ABOUT OUTPUT FORMATTING

bool DISPLAY_FINAL_GRADE = false;
bool DISPLAY_MOSS_DETAILS = false;
bool DISPLAY_GRADE_DETAILS = false;
bool DISPLAY_EXAM_SEATING = false;

std::vector<std::string> MESSAGES;


//====================================================================

std::ofstream priority_stream("priority.txt");
std::ofstream late_days_stream("late_days.txt");

void PrintExamRoomAndZoneTable(std::ofstream &ostr, Student *s);

//====================================================================


// lookup a student by username
Student* GetStudent(const std::vector<Student*> &students, const std::string& username) {
  for (int i = 0; i < students.size(); i++) {
    if (students[i]->getUserName() == username) return students[i];
  }
  return NULL;
}



//====================================================================
// sorting routines 


bool by_overall(const Student* s1, const Student* s2) {
  return (s1->overall() > s2->overall());
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

bool by_iclickertotal(const Student* s1, const Student* s2) {
  return (s1->getIClickerTotalFromStart() > s2->getIClickerTotalFromStart());
}

int convertMajor(const std::string &major);
int convertYear(const std::string &major);

bool by_year(const Student *s1, const Student *s2) {
  int y1 = convertYear(s1->getYear());
  int y2 = convertYear(s2->getYear());
  if (y1 == y2) {
    return by_overall(s1,s2);
  } 
  return (y1 < y2);
}



bool by_major(const Student *s1, const Student *s2) {
  int m1 = std::max(convertMajor(s1->getMajor1()),
                    convertMajor(s1->getMajor2()));
  int m2 = std::max(convertMajor(s2->getMajor1()),
                    convertMajor(s2->getMajor2()));
  if (m1 == m2) {
    return by_year(s1,s2);
  } 
  return (m1 > m2);
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
  istr >> GRADEABLES_COUNT[g] >> GRADEABLES_FIRST[g] >> GRADEABLES_PERCENT[g] >> GRADEABLES_MAXIMUM[g];
  assert (GRADEABLES_COUNT[g] >= 0);
  assert (GRADEABLES_PERCENT[g] >= 0.0 && GRADEABLES_PERCENT[g] <= 1.0);
  assert (GRADEABLES_MAXIMUM[g] >= 0.0);
}


bool string_to_gradeable_enum(const std::string &s, GRADEABLE_ENUM &return_value) {
  if (s == "reading")               { return_value = GRADEABLE_ENUM::READING;        return true;  }
  if (s == "exercise")              { return_value = GRADEABLE_ENUM::EXERCISE;       return true;  }
  if (s == "lab" || s == "Lab")     { return_value = GRADEABLE_ENUM::LAB;            return true;  }
  if (s == "participation")         { return_value = GRADEABLE_ENUM::PARTICIPATION;  return true;  }
  if (s == "hw" || s == "homework") { return_value = GRADEABLE_ENUM::HOMEWORK;       return true;  }
  if (s == "project")               { return_value = GRADEABLE_ENUM::PROJECT;        return true;  }
  if (s == "quiz")                  { return_value = GRADEABLE_ENUM::QUIZ;           return true;  }
  if (s == "test")                  { return_value = GRADEABLE_ENUM::TEST;           return true;  }
  if (s == "exam")                  { return_value = GRADEABLE_ENUM::EXAM;           return true;  }
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


    } else if (token == "display") {
      istr >> token;
      if (token == "exam_seating") {
        DISPLAY_EXAM_SEATING = true;
      } else if (token == "moss_details") {
        DISPLAY_MOSS_DETAILS = true;
      } else if (token == "grade_details") {
        DISPLAY_GRADE_DETAILS = true;
      } else if (token == "final_grade") {
        DISPLAY_FINAL_GRADE = true;
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
        assert (num >= 0 && num < GRADEABLES_COUNT[GRADEABLE_ENUM::HOMEWORK]);
        GRADEABLES_REMOVE_LOWEST[GRADEABLE_ENUM::HOMEWORK] = num;
      }
    }
  }
}


void MakeRosterFile(std::vector<Student*> &students) {

  std::sort(students.begin(),students.end(),by_name);

  std::ofstream ostr("./iclicker_Roster.txt");


  for (int i = 0; i < students.size(); i++) {
    std::string foo = "active";
    if (students[i]->getLastName() == "") continue;
    if (students[i]->getSection() <= 0 || students[i]->getSection() > 10) continue;
    if (students[i]->getGradeableValue(GRADEABLE_ENUM::TEST,0) < 1) {
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


class ZoneAssignment {

public:

  std::string building;
  std::string room;
  std::string zone;
  
};


// random generator function:
int myrandomzone (int i) { return std::rand()%i;}


void LoadExamSeatingFile(const std::string &zone_counts_filename, const std::string &zone_assignments_filename, std::vector<Student*> &students) {
  std::cout << "zone counts filename " << zone_counts_filename << std::endl;
  std::cout << "zone assignments filename " << zone_assignments_filename << std::endl;

  // ============================================================
  // read in any existing assignments...

  std::map<std::string,int> zones;
  
  {
    std::ifstream istr_zone_assignments(zone_assignments_filename.c_str());
    if (istr_zone_assignments.good()) {
      std::string line;
      while (getline(istr_zone_assignments,line)) {
        std::stringstream ss(line.c_str());
        std::string token,last,first,rcs,building,room,zone,time;
        ss >> last >> first >> rcs >> building >> room >> zone >> time;
        if (last == "") break;
        Student *s = GetStudent(students,rcs);
        if (s == NULL) {
          std::cout << "**************************NOT HERE" << rcs << std::endl;
          continue;
        }
        assert (s != NULL);
        if (zone != "") {
          s->setExamRoom(building+std::string(" ")+room);
          s->setExamZone(zone);
          if (time != "") {
            s->setExamTime(time);
          }
          zones[zone]++;
        }
      }
    }
  }


  // ============================================================
  // read in the desired zone counts

  // create a vector of zones to assign
  std::vector<ZoneAssignment> zone_assignments;
  std::map<std::string,int> max_per_zone;
  std::map<std::string,std::string> zone_room_assignment;

  {
    std::ifstream istr_zone_counts(zone_counts_filename.c_str());
    assert (istr_zone_counts.good());
    
    int count;
    ZoneAssignment za; 
    while (istr_zone_counts >> za.zone >> za.building >> za.room >> count) {

      max_per_zone[za.zone] = count;
      zone_room_assignment[za.zone] = za.building + " " + za.room;

      int sofar = 0;
      if (zones.find(za.zone) != zones.end()) {
        sofar = zones.find(za.zone)->second;
      }

      assert (sofar <= count);

      for (int i = sofar; i < count; i++) {
        zone_assignments.push_back(za);
      }
    }

    // FIXME: this belongs once, at start of program
    std::srand ( unsigned ( std::time(0) ) );
    
    std::random_shuffle ( zone_assignments.begin(), zone_assignments.end(), myrandomzone );
  }



  int total_assignments = 0;
  for (std::map<std::string,int>::iterator itr = zones.begin();
       itr != zones.end(); itr++) {

    assert (zone_room_assignment.find(itr->first) != zone_room_assignment.end());
    std::cout << "ZONE " << itr->first << " " << itr->second << "  " 
              << zone_room_assignment.find(itr->first)->second << std::endl;
    total_assignments += itr->second;
  }
  std::cout << "TOTAL " << total_assignments << std::endl;



  // ============================================================
  // do the assignments!

  int not_reg = 0;
  int no_grades = 0;
  int new_zone_assign = 0;
  int already_zoned = 0;
  int next_za = 0;

  for (int i = 0; i < students.size(); i++) {

    Student* &s = students[i];

    if (s->getExamRoom() != "") {
      already_zoned++;
    } else if (!validSection(s->getSection())) {
      not_reg++;
    } else if (s->overall() < 1.0) {
      no_grades++;
    } else {
      assert (next_za < zone_assignments.size());
      const ZoneAssignment &za = zone_assignments[next_za];
      s->setExamRoom(za.building+std::string(" ")+za.room);
      s->setExamZone(za.zone);
      next_za++;
      new_zone_assign++;
    }
  }
  
  std::cout << "new zone assign " << new_zone_assign << std::endl;
  std::cout << "no grades       " << no_grades << std::endl;
  std::cout << "not reg         " << not_reg << std::endl;

  std::cout << "za.size()       " << zone_assignments.size() << std::endl;

  assert (new_zone_assign <= zone_assignments.size());

  // ============================================================
  // write out the assignments!


  {
    std::ofstream ostr_zone_assignments(zone_assignments_filename.c_str());
    assert (ostr_zone_assignments.good());
  
    for (int i = 0; i < students.size(); i++) {
      
      Student* &s = students[i];
      
      if (s->getLastName() == "") continue;

      ostr_zone_assignments << std::setw(20) << std::left << s->getLastName()  << " ";
      ostr_zone_assignments << std::setw(15) << std::left << s->getFirstName() << " ";
      ostr_zone_assignments << std::setw(12) << std::left << s->getUserName()  << " ";

      ostr_zone_assignments << std::setw(10) << std::left << s->getExamRoom()  << " ";
      ostr_zone_assignments << std::setw(10) << std::left << s->getExamZone()  << " ";
      ostr_zone_assignments << std::setw(10) << std::left << s->getExamTime();
      
      ostr_zone_assignments << std::endl;

    }
  }



}




void processcustomizationfile(std::vector<Student*> &students, bool students_loaded) {

  Student *blank    = GetStudent(students,"");
  Student *perfect  = GetStudent(students,"PERFECT");
  Student *lowest_a = GetStudent(students,"LOWEST A-");
  Student *lowest_b = GetStudent(students,"LOWEST B-");
  Student *lowest_c = GetStudent(students,"LOWEST C-");
  Student *lowest_d = GetStudent(students,"LOWEST D");

  if (students_loaded == false) {
    preprocesscustomizationfile();
    blank    = new Student();
    perfect  = new Student();perfect->setUserName("PERFECT");
    lowest_a = new Student();lowest_a->setUserName("LOWEST A-");lowest_a->setFirstName("approximate");
    lowest_b = new Student();lowest_b->setUserName("LOWEST B-");lowest_b->setFirstName("approximate");
    lowest_c = new Student();lowest_c->setUserName("LOWEST C-");lowest_c->setFirstName("approximate");
    lowest_d = new Student();lowest_d->setUserName("LOWEST D"); lowest_d->setFirstName("approximate");

    PERFECT_STUDENT_POINTER = perfect;
  } 


  std::ifstream istr(CUSTOMIZATION_FILE.c_str());
  assert (istr);

  std::string token,token2;
  int num;
  int which;
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
        assert (!validSection(section)); //sectionNames.find(section) == sectionNames.end());
        sectionNames[section] = section_name;
        
        static int counter = 0;
        if (sectionColors.find(section_name) == sectionColors.end()) {
          if (counter == 0) {
            sectionColors[section_name] = "ffff88"; //yellow
          } else if (counter == 1) {
            sectionColors[section_name] = "ff88aa"; //pink
          } else if (counter == 2) {
            sectionColors[section_name] = "ffcc88"; //orange 
          } else if (counter == 3) {
            sectionColors[section_name] = "aaaaaa"; // grey 
          } else if (counter == 4) {
            sectionColors[section_name] = "aaeeff"; //lt blue
          } else if (counter == 5) {
            sectionColors[section_name] = "8888ff"; // purple 
          } else if (counter == 6) {
            sectionColors[section_name] = "aaff88"; //green
          } else {
            sectionColors[section_name] = "ffffff"; // white
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



    } else if (token == "exam_seating") {

      DISPLAY_EXAM_SEATING = true;

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

      char gradesline[1000];
      istr.getline(gradesline,1000);

      std::stringstream ss(gradesline);
      
      if (!(ss >> which >> p_score)) {
        std::cout << "ERROR READING" << std::endl;
        std::cout << which << " " << p_score << std::endl;
        
        assert (0);
        exit(1);
      }
      a_score = 0.9*p_score;
      b_score = 0.8*p_score;
      c_score = 0.7*p_score;
      d_score = 0.6*p_score;
      ss >> a_score >> b_score >> c_score >> d_score;

      //std::cout << "HERE " << which << " " << p_score << " " << a_score << " " << b_score << " " << c_score << " " << d_score << std::endl;
      assert (p_score >= a_score &&
              a_score >= b_score &&
              b_score >= c_score &&
              c_score >= d_score);

      GRADEABLE_ENUM g;
      bool success = string_to_gradeable_enum(token,g);
      
      if (success) {
        assert (which >= GRADEABLES_FIRST[g] && which < GRADEABLES_COUNT[g]+GRADEABLES_FIRST[g]);
        perfect->setGradeableValue(g,which-GRADEABLES_FIRST[g], p_score);
        lowest_a->setGradeableValue(g,which-GRADEABLES_FIRST[g], a_score);
        lowest_b->setGradeableValue(g,which-GRADEABLES_FIRST[g], b_score);
        lowest_c->setGradeableValue(g,which-GRADEABLES_FIRST[g], c_score);
        lowest_d->setGradeableValue(g,which-GRADEABLES_FIRST[g], d_score);
      } else {
        std::cout << "ERROR: UNKNOWN TOKEN  X" << token << std::endl;
      }

    }
  }
  
  if (students_loaded == false) {
    students.push_back(blank);
    students.push_back(perfect);
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

  std::string command = "ls -1 " + RAW_DATA_DIRECTORY + "*.txt > files.txt";

  system(command.c_str());

  std::ifstream files_istr("files.txt");
  assert(files_istr);
  std::string filename;
  int count = 0;
  while (files_istr >> filename) {
    //std::ifstream istr((RAW_DATA_DIRECTORY+filename).c_str());
    std::ifstream istr(filename.c_str());
    assert (istr);
    Student *s = new Student();

    count++;

    std::string token;
    int a;
    float b;

    while (istr >> token) {

      GRADEABLE_ENUM g;
      bool gradeable_enum_success = string_to_gradeable_enum(token,g);

      //std::cout << "my TOKEN " << " " << token << std::endl;

      if (token == "rcs_id") {
        istr >> token;
        s->setUserName(token);
      } else if (token == "first_name") {
	std::string rest_of_line;
	getline(istr,rest_of_line);
	std::stringstream ss(rest_of_line);
        ss >> token;
        s->setFirstName(token);
      } else if (token == "last_name") {
	std::string rest_of_line;
	getline(istr,rest_of_line);
	std::stringstream ss(rest_of_line);
        ss >> token;
        s->setLastName(token);
      } else if (token == "section") {
        istr >> a;
        assert (a >= 0 && a <= MAX_SECTION);
        if (!validSection(a)) {
          std::cout << "WARNING: invalid section " << a << std::endl;
        }
          //sectionNames[a] == "FAKE") {  a = 0;  }
        s->setSection(a);
      } else if (token == "exam_seating") {
        std::cout << "TOKEN IS EXAM SEATING" << std::endl;
        char line[MAX_STRING_LENGTH];
        istr.getline(line,MAX_STRING_LENGTH);
      } else if (token == "academic" || token == "Academic") {
        istr >> token; 
        assert (token == "integrity" || token == "Integrity");
        istr >> a; 
        assert (a == 0 || a == 1);
	s->setAcademicIntegrityForm();
      } else if (token == "Participation") {
        istr >> a; 
        assert (a >= 0 && a <= 5);
        s->setParticipation(a);
      } else if (token == "Understanding") {
        istr >> a; 
        assert (a >= 0 && a <= 5);
        s->setUnderstanding(a);
      } 

      
      else if (gradeable_enum_success) {
          
        std::string line;
        getline(istr,line);
        std::stringstream ss(line.c_str());
        
        
        int which;
        float value;
        std::string label;
        ss >> which >> value >> label;

        if (which < GRADEABLES_FIRST[g]) {
          std::cerr << gradeable_to_string(g) << " " << which << " " << GRADEABLES_FIRST[g] << std::endl;
        }
        
        assert (which >= GRADEABLES_FIRST[g]);
        assert (value >= 0.0);
        
        
        // FIXME: this renaming should go away!!!
        if (which >= GRADEABLES_COUNT[g]+GRADEABLES_FIRST[g]) {
          if (g == GRADEABLE_ENUM::LAB) {
            // assume these labs are really participation & understanding grades
            if (which == 16) {
              s->setParticipation(value);
            } else {
              assert (which == 17);
              s->setUnderstanding(value);
            }
          } else if (g == GRADEABLE_ENUM::TEST) {
            if (which > GRADEABLES_COUNT[g]) {
              // must assume this is an exam
              int exam_which = which-GRADEABLES_COUNT[GRADEABLE_ENUM::TEST]-GRADEABLES_FIRST[GRADEABLE_ENUM::EXAM]; 
              assert (exam_which<= GRADEABLES_COUNT[GRADEABLE_ENUM::EXAM]);
              s->setGradeableValue(GRADEABLE_ENUM::EXAM,exam_which,value);
              s->setTestZone(which-GRADEABLES_FIRST[GRADEABLE_ENUM::TEST],label);
            }
          }
        }
        
        else {

          //s->setGradeableValue(g,which-GRADEABLES_FIRST[g],22);
          /*
   if (g == GRADEABLE_ENUM::HOMEWORK) {
            value = 0;
          }
          */
          s->setGradeableValue(g,which-GRADEABLES_FIRST[g],value);
          
          if (label != "") {
            if (g == GRADEABLE_ENUM::TEST) {
              s->setTestZone(which-GRADEABLES_FIRST[g],label);
            } else {
              assert (g == GRADEABLE_ENUM::EXAM);
              s->setTestZone(which-GRADEABLES_FIRST[g]+GRADEABLES_COUNT[GRADEABLE_ENUM::TEST],label);
            }
          }
        }
      }
      
      
      else if (token == "days_late") {
        istr >> token; 
        if (token.size() < 4) {
          std::cout << "problem with days late format for " << s->getUserName() << std::endl;
        }
        assert(token.size() >= 4);
        assert (token.substr(0,3) == "hw_");
        int which = atoi(token.substr(3,token.size()-3).c_str())-1;
        assert(which >= 0 && which < GRADEABLES_COUNT[GRADEABLE_ENUM::HOMEWORK]);
        istr >> a;
        if (a < 1 || a > 3) {
          std::cout << "suspicious late days (" << a << ") for student " << s->getUserName() << " on hw " << which << std::endl;
        }
        if (s->getGradeableValue(GRADEABLE_ENUM::HOMEWORK,which) == 0) {
          a = 0;
        }
        if (a == 3) {
          std::cout << "LATE DAYS " << a << " " << s->getUserName() << " " << token << std::endl;
        }
        s->incrLateDaysUsed(which,a);

      } 

      else {
        std::cout << "UNKNOWN TOKEN  Y" << token << std::endl;
        exit(0);
      }
    }
    
    students.push_back(s);
  }
}


void start_table(std::ofstream &ostr, std::string &filename, bool full_details, 
                 const std::vector<Student*> &students, int S, int month, int day, int year);

void output_line(std::ofstream &ostr, 
                 int part,
                 bool full_details, 
                 Student *this_student,
                 int rank,
                 Student *sp, Student *sa, Student *sb, Student *sc, Student *sd);




void end_table(std::ofstream &ostr,  bool full_details, const std::vector<Student*> &students, int S);



// =============================================================================================
// =============================================================================================
// =============================================================================================




void output_helper(  std::vector<Student*> &students,
                     std::string &sort_order ) {


  Student *blank = GetStudent(students,"");
  Student *sp = GetStudent(students,"PERFECT");
  Student *sa = GetStudent(students,"LOWEST A-");
  Student *sb = GetStudent(students,"LOWEST B-");
  Student *sc = GetStudent(students,"LOWEST C-");
  Student *sd = GetStudent(students,"LOWEST D");
  assert (blank != NULL);
  assert (sp != NULL);
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

  start_table(ostr,summary_file,true,students,-1,month,day,year);

  int next_rank = 1;
  int last_section = -1;

  //  for (int part = 0; part < 1; part++) {

    for (int S = 0; S < (int)students.size(); S++) {
      if (students[S] == blank) continue;
      int rank = next_rank;
      if (students[S] == sp ||
          students[S] == sa ||
          students[S] == sb ||
          students[S] == sc ||
          students[S] == sd ||
          students[S]->getUserName() == "" ||
          sectionNames[students[S]->getSection()] == "") {
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
      output_line(ostr,0,true,this_student,rank,sp,sa,sb,sc,sd);
    }
    
    ostr << "</table>\n";
    end_table(ostr,true,students,-1);
    
    command = "ln -s " + summary_file + " " + OUTPUT_FILE;
    system(command.c_str());
    
    for (int S = 0; S < (int)students.size(); S++) {
      if (students[S]->getSection() == 0) continue;
      std::string file = INDIVIDUAL_FILES_OUTPUT_DIRECTORY + students[S]->getUserName() + "_summary.html";
      start_table(ostr,file,false,students,S,month,day,year);
      output_line(ostr,0,false,students[S],-1,sp,sa,sb,sc,sd);
      output_line(ostr,0,false,blank,-1,sp,sa,sb,sc,sd);
      output_line(ostr,0,false,sp,-1,sp,sa,sb,sc,sd);
      output_line(ostr,0,false,sa,-1,sp,sa,sb,sc,sd);
      output_line(ostr,0,false,sb,-1,sp,sa,sb,sc,sd);
      output_line(ostr,0,false,sc,-1,sp,sa,sb,sc,sd);
      output_line(ostr,0,false,sd,-1,sp,sa,sb,sc,sd);
      
      ostr << "</table><br><br>\n";


    bool any_notes = false;


    end_table(ostr,true,students,S);



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

    int prev = -1;
    for (int i = 1; i <= 10; i++) {
      int tmp = students[S]->getAllowedLateDays(i);
      if (prev != tmp) {
        late_days_stream << students[S]->getUserName() << " " << i << " " << tmp << std::endl;
        prev = tmp;
      }
    }
    }
    
}

    
// =============================================================================================
// =============================================================================================
// =============================================================================================





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

  // ======================================================================
  // MAKE FAKE STUDENTS FOR THE CURVES
  processcustomizationfile(students,true); 

  // ======================================================================
  // SORT
  std::sort(students.begin(),students.end(),by_overall);


  if (sort_order == std::string("by_overall")) {
    std::sort(students.begin(),students.end(),by_overall);
  } else if (sort_order == std::string("by_iclickertotal")) {
    std::sort(students.begin(),students.end(),by_iclickertotal);
  } else if (sort_order == std::string("by_name")) {
    std::sort(students.begin(),students.end(),by_name);
  } else if (sort_order == std::string("by_year")) {
    std::sort(students.begin(),students.end(),by_year);
  } else if (sort_order == std::string("by_major")) {
    std::sort(students.begin(),students.end(),by_major);
  } else if (sort_order == std::string("by_section")) {
    std::sort(students.begin(),students.end(),by_section);
  } else {
    assert (sort_order.size() > 7);
    GRADEABLE_ENUM g;
    // take off "by_" and "_pct"
    std::string tmp = sort_order.substr(3,sort_order.size()-7);
    bool success = string_to_gradeable_enum(tmp,g);
    if (success) {
      std::sort(students.begin(),students.end(), GradeableSorter(g) );
    }
    else {
      std::cerr << "UNKNOWN SORT OPTION " << sort_order << std::endl;
      std::cerr << "  Usage: " << argv[0] << " [ by_overall | by_section | by_lab_pct | by_exercise | by_reading_pct | by_hw_pct | by_test_pct | by_exam_pct ]" << std::endl;
      exit(1);
    }
  }



  // ======================================================================
  // COUNT

  for (unsigned int s = 0; s < students.size(); s++) {
    if (students[s]->getLastName() == "" || students[s]->getFirstName() == "") continue;
    if (students[s]->getAudit()) {
      auditors++;
      continue;
    }

    Student *sd = GetStudent(students,"LOWEST D");


    grade_counts[students[s]->grade(false,sd)]++;
    grade_avg[students[s]->grade(false,sd)]+=students[s]->overall();

    if (GRADEABLES_COUNT[GRADEABLE_ENUM::TEST] != 0) {
      if (students[s]->getGradeableValue(GRADEABLE_ENUM::TEST,GRADEABLES_COUNT[GRADEABLE_ENUM::TEST]-1) > 0) took_final++;
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

  // ======================================================================
  // SUGGEST CURVES

  for (int i = 0; i < GRADEABLES_COUNT[GRADEABLE_ENUM::HOMEWORK]; i++) {
    std::cout << "HOMEWORK " << i+1 << " statistics & suggested curve" << std::endl;
    std::vector<float> scores;
    for (int S = 0; S < students.size(); S++) {
      if (students[S]->getSection() > 0 && students[S]->getGradeableValue(GRADEABLE_ENUM::HOMEWORK,i) > 0) {
        scores.push_back(students[S]->getGradeableValue(GRADEABLE_ENUM::HOMEWORK,i));
      }
    }
    if (scores.size() > 0) {
      std::cout << "   " << scores.size() << " submitted" << std::endl;
      std::sort(scores.begin(),scores.end());
      float sum = 0;
      for (int i = 0; i < scores.size(); i++) {
        sum+=scores[i];
      }
      float average = sum / float(scores.size());
      std::cout << "    average " << average << std::endl;
      sum = 0;
      for (int i = 0; i < scores.size(); i++) {
        sum+=(average-scores[i])*(average-scores[i]);
      }
      float stddev = sqrt(sum/float(scores.size()));
      std::cout << "    stddev " << stddev << std::endl;

      std::cout << "    A- cutoff " << scores[int(0.7*scores.size())] << std::endl;
      std::cout << "    B- cutoff " << scores[int(0.45*scores.size())] << std::endl;
      std::cout << "    C- cutoff " << scores[int(0.2*scores.size())] << std::endl;
      std::cout << "    D  cutoff " << scores[int(0.1*scores.size())] << std::endl;


    }
  }







  for (int i = 0; i < GRADEABLES_COUNT[GRADEABLE_ENUM::TEST]; i++) {
    std::cout << "TEST " << i+1 << std::endl;
    std::vector<float> scores;

    std::map<int, int> section_counts;

    for (int S = 0; S < students.size(); S++) {
      if (students[S]->getSection() > 0 && students[S]->getGradeableValue(GRADEABLE_ENUM::TEST,i) > 0) {
        scores.push_back(students[S]->getGradeableValue(GRADEABLE_ENUM::TEST,i));
        section_counts[students[S]->getSection()]++;
      }
    }
    if (scores.size() > 0) {
      std::cout << "   " << scores.size() << " tests" << std::endl;
      std::sort(scores.begin(),scores.end());
      float sum = 0;
      for (int i = 0; i < scores.size(); i++) {
        sum+=scores[i];
      }
      float average = sum / float(scores.size());
      std::cout << "    average " << average << std::endl;
      sum = 0;
      for (int i = 0; i < scores.size(); i++) {
        sum+=(average-scores[i])*(average-scores[i]);
      }
      float stddev = sqrt(sum/float(scores.size()));
      std::cout << "    stddev " << stddev << std::endl;

      std::cout << "    A- cutoff " << scores[int(0.7*scores.size())] << std::endl;
      std::cout << "    B- cutoff " << scores[int(0.45*scores.size())] << std::endl;
      std::cout << "    C- cutoff " << scores[int(0.2*scores.size())] << std::endl;
      std::cout << "    D  cutoff " << scores[int(0.1*scores.size())] << std::endl;

      std::cout << "    max " << scores.back() << std::endl;
      std::cout << "    min " << scores.front() << std::endl;
    }

    int total = 0;
    for (std::map<int,int>::iterator itr = section_counts.begin(); itr != section_counts.end(); itr++) {
      std::cout << "SECTION " << std::setw(2) << itr->first << "  has this # of tests:  " << std::setw(2) << itr->second << std::endl;
      total += itr->second;
    }
    std::cout << "TOTAL = " << total << std::endl;

  }
}

// =============================================================================================
// =============================================================================================
// =============================================================================================

