#include <iostream>
#include <cassert>
#include <fstream>
#include <sstream>
#include <string>
#include <vector>
#include <iomanip>
#include <map>
#include <algorithm>
#include <ctime>
#include <cmath>
#include "benchmark.h"

std::string GLOBAL_sort_order;


int GLOBAL_ACTIVE_TEST_ZONE = 0;

#include "student.h"
#include "iclicker.h"
#include "gradeable.h"
#include "grade.h"
#include "json.hpp"

// defined in iclicker.cpp
std::string ReadQuoted(std::istream &istr);
void suggest_curves(std::vector<Student*> &students);

std::string GLOBAL_recommend_id = "";


//====================================================================
// DIRECTORIES & FILES

std::string ICLICKER_ROSTER_FILE              = "./iclicker_Roster.txt";
std::string OUTPUT_FILE                       = "./output.html";
std::string CUSTOMIZATION_FILE                = "./customization_no_comments.json";

std::string RAW_DATA_DIRECTORY                = "./raw_data/";
std::string INDIVIDUAL_FILES_OUTPUT_DIRECTORY = "./individual_summary_html/";
std::string ALL_STUDENTS_OUTPUT_DIRECTORY     = "./all_students_summary_html/";

nlohmann::json GLOBAL_CUSTOMIZATION_JSON;


//====================================================================
// INFO ABOUT GRADING FOR COURSE

std::vector<GRADEABLE_ENUM> ALL_GRADEABLES;

std::map<GRADEABLE_ENUM,Gradeable>  GRADEABLES;

float LATE_DAY_PERCENTAGE_PENALTY = 0;
bool  TEST_IMPROVEMENT_AVERAGING_ADJUSTMENT = false;
bool  LOWEST_TEST_COUNTS_HALF = false;

int QUIZ_NORMALIZE_AND_DROP = 0;

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
Student* STDDEV_STUDENT_POINTER;

//====================================================================
// INFO ABOUT NUMBER OF SECTIONS

std::map<int,std::string> sectionNames;
std::map<std::string,std::string> sectionColors;

bool validSection(int section) {

  nlohmann::json::iterator itr = GLOBAL_CUSTOMIZATION_JSON.find("section");
  assert (itr != GLOBAL_CUSTOMIZATION_JSON.end());
  assert (itr->is_object());

  nlohmann::json::iterator itr2 = itr->find(std::to_string(section));
  if (itr2 == itr->end()) return false;
  return true;
  
  //return (sectionNames.find(section) != sectionNames.end());
}


std::string sectionName(int section) {
  std::map<int,std::string>::const_iterator itr = sectionNames.find(section);
  if (itr == sectionNames.end()) 
    return "NONE";
  return itr->second;
}




//====================================================================
 
std::string GLOBAL_EXAM_TITLE = "exam title uninitialized";
std::string GLOBAL_EXAM_DATE = "exam date uninitialized";
std::string GLOBAL_EXAM_TIME = "exam time uninitialized";
std::string GLOBAL_EXAM_DEFAULT_ROOM = "exam default room uninitialized";
std::string GLOBAL_EXAM_SEATING = "";
std::string GLOBAL_SEATING_SPACING = "";
std::string GLOBAL_EXAM_SEATING_COUNT = "";
std::string GLOBAL_LEFT_RIGHT_HANDEDNESS = "";

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
bool DISPLAY_LATE_DAYS = false;


std::vector<std::string> MESSAGES;


//====================================================================

std::ofstream priority_stream("priority.txt");
std::ofstream late_days_stream("late_days.txt");

void PrintExamRoomAndZoneTable(std::ofstream &ostr, Student *s, const nlohmann::json &special_message);

//====================================================================





//====================================================================
// sorting routines 


bool by_overall(const Student* s1, const Student* s2) {
  float s1_overall = s1->overall_b4_moss();
  float s2_overall = s2->overall_b4_moss();

  if (s1 == AVERAGE_STUDENT_POINTER) return true;
  if (s2 == AVERAGE_STUDENT_POINTER) return false;
  if (s1 == STDDEV_STUDENT_POINTER) return true;
  if (s2 == STDDEV_STUDENT_POINTER) return false;
  
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
           s1->getPreferredName() < s2->getPreferredName()));
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
/*
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
*/

bool string_to_gradeable_enum(const std::string &s, GRADEABLE_ENUM &return_value) {
  std::string s2;
  for (unsigned int i = 0; i < s.size(); ++i) {
    s2.push_back(std::tolower(s[i]));
  }
  std::replace( s2.begin(), s2.end(), '-', '_');
  if (s2 == "hw" || s2 == "homework")          { return_value = GRADEABLE_ENUM::HOMEWORK;          return true;  }
  if (s2 == "assignment")                      { return_value = GRADEABLE_ENUM::ASSIGNMENT;        return true;  }
  if (s2 == "problem_set")                     { return_value = GRADEABLE_ENUM::PROBLEM_SET;       return true;  }
  if (s2 == "quiz" || s2 == "quizze")          { return_value = GRADEABLE_ENUM::QUIZ;              return true;  }
  if (s2 == "test")                            { return_value = GRADEABLE_ENUM::TEST;              return true;  }
  if (s2 == "exam")                            { return_value = GRADEABLE_ENUM::EXAM;              return true;  }
  if (s2 == "exercise")                        { return_value = GRADEABLE_ENUM::EXERCISE;          return true;  }
  if (s2 == "lecture_exercise")                { return_value = GRADEABLE_ENUM::LECTURE_EXERCISE;  return true;  }
  if (s2 == "reading")                         { return_value = GRADEABLE_ENUM::READING;           return true;  }
  if (s2 == "lab")                             { return_value = GRADEABLE_ENUM::LAB;               return true;  }
  if (s2 == "recitation")                      { return_value = GRADEABLE_ENUM::RECITATION;        return true;  }
  if (s2 == "project")                         { return_value = GRADEABLE_ENUM::PROJECT;           return true;  }
  if (s2 == "participation")                   { return_value = GRADEABLE_ENUM::PARTICIPATION;     return true;  }
  if (s2 == "instructor_note" || s2 == "note") { return_value = GRADEABLE_ENUM::NOTE;              return true;  }
  if (s2 == "note")                            { return_value = GRADEABLE_ENUM::NOTE;              return true;  }
  if (s2.substr(0,4) == "none")                { return_value = GRADEABLE_ENUM::NOTE;              return true;  }
  return false;
}

//====================================================================


void preprocesscustomizationfile(std::vector<Student*> &students) {  
  /*
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

    } else if (token == "display_benchmark") {
      istr >> token;
      DisplayBenchmark(token);

    } else if (token == "benchmark_percent") {
      istr >> token;
      float value;
      istr >> value;

      SetBenchmarkPercentage(token,value);

    } else if (token == "benchmark_color") {
      istr >> token;
      std::string color;
      istr >> color;

      SetBenchmarkColor(token,color);
      
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
  */
  // ---------------------------------------------------------------------------------------
  
  std::ifstream istr(CUSTOMIZATION_FILE.c_str());
  assert (istr);
  std::string token;
  float p_score,a_score,b_score,c_score,d_score;
  
  Student *perfect  = GetStudent(students,"PERFECT");
  Student *student_average  = GetStudent(students,"AVERAGE");
  Student *student_stddev  = GetStudent(students,"STDDEV");
  Student *lowest_a = GetStudent(students,"LOWEST A-");
  Student *lowest_b = GetStudent(students,"LOWEST B-");
  Student *lowest_c = GetStudent(students,"LOWEST C-");
  Student *lowest_d = GetStudent(students,"LOWEST D");
  
  //std::cout << "0" << std::endl;
  
  nlohmann::json j;
  j << istr;
  
  // load gradeables
  nlohmann::json all_gradeables = j["gradeables"];
  int num_gradeables = all_gradeables.size();

  float sum_of_percents = 0;

  for (int i = 0; i < num_gradeables; i++) {
    nlohmann::json one_gradeable_type = all_gradeables[i];
    GRADEABLE_ENUM g;

    // TYPE
    nlohmann::json::iterator itr = one_gradeable_type.find("type");
    assert (itr != one_gradeable_type.end());
    std::string gradeable_type = itr->get<std::string>();
    bool success = string_to_gradeable_enum(gradeable_type, g);
    if (!success) {
      std::cout << "UNKNOWN GRADEABLE TYPE: " << gradeable_type << std::endl;
      exit(0);
    }

    // PERCENT
    itr = one_gradeable_type.find("percent");
    assert (itr != one_gradeable_type.end());
    float gradeable_total_percent = itr->get<float>();
    assert (gradeable_total_percent >= 0);
    sum_of_percents += gradeable_total_percent;

    int count = one_gradeable_type.value("count",-1);

    nlohmann::json ids_list = one_gradeable_type["ids"];
    for (unsigned int k = 0; k < ids_list.size(); k++) {
      nlohmann::json ids = ids_list[k];
      std::string gradeable_id = ids.value("id","");
      assert (gradeable_id != "");
    }
    if (count == -1) {
      count = ids_list.size();
    }
    //If we ever have more than INT_MAX ids, this will wrap.
    //Needed to resolve int vs uint comparison and this is preferable since
    //presumably the json library is spitting out an int and the customization.json
    //also assumes int.
    assert (int(ids_list.size()) <= count);

    Gradeable answer (count,gradeable_total_percent); //,m);
    GRADEABLES.insert(std::make_pair(g,answer));
    assert (GRADEABLES[g].getCount() >= 0);
    assert (GRADEABLES[g].getPercent() >= 0.0 && GRADEABLES[g].getPercent() <= 1.0);
  
    // Set remove lowest for gradeable
    int num = one_gradeable_type.value("remove_lowest", 0);
    assert (num == 0 || (num >= 0 && num < GRADEABLES[g].getCount()));
    GRADEABLES[g].setRemoveLowest(num);
    ALL_GRADEABLES.push_back(g);
  }
  
  // Set Benchmark Percent

  std::vector<std::string> benchmark_percents;

  nlohmann::json benchmarkPercent = j["benchmark_percent"];
  for (nlohmann::json::iterator itr = benchmarkPercent.begin(); itr != benchmarkPercent.end(); itr++) {
    token = itr.key();
    float value;
    value = itr.value();
    SetBenchmarkPercentage(token,value);
    benchmark_percents.push_back(token);
  }



  perfect = new Student();perfect->setUserName("PERFECT");
  student_average = new Student();student_average->setUserName("AVERAGE");
  student_stddev = new Student();student_stddev->setUserName("STDDEV");

  lowest_a = new Student();lowest_a->setUserName("LOWEST A-");lowest_a->setLegalFirstName("approximate");
  lowest_b = new Student();lowest_b->setUserName("LOWEST B-");lowest_b->setLegalFirstName("approximate");
  lowest_c = new Student();lowest_c->setUserName("LOWEST C-");lowest_c->setLegalFirstName("approximate");
  lowest_d = new Student();lowest_d->setUserName("LOWEST D"); lowest_d->setLegalFirstName("approximate");

  PERFECT_STUDENT_POINTER = perfect;
  AVERAGE_STUDENT_POINTER = student_average;
  STDDEV_STUDENT_POINTER = student_stddev;
  
  for (int i = 0; i < num_gradeables; i++) {

    nlohmann::json one_gradeable_type = all_gradeables[i];

    GRADEABLE_ENUM g;
    nlohmann::json::iterator itr = one_gradeable_type.find("type");
    assert (itr != one_gradeable_type.end());
    std::string gradeable_type = itr->get<std::string>();
    bool success = string_to_gradeable_enum(gradeable_type, g);
    if (!success) {
      std::cout << "UNKNOWN GRADEABLE: " << gradeable_type << std::endl;
      exit(0);
    }
    nlohmann::json ids_list = one_gradeable_type["ids"];

    for (unsigned int k = 0; k < ids_list.size(); k++) {
      nlohmann::json grade_id = ids_list[k];
      std::string token_key = grade_id.value("id","");
      assert (token_key != "");

      int which = GRADEABLES[g].setCorrespondence(token_key);
      p_score = grade_id.value("max", 0.0);

      std::vector<float> curve = grade_id.value("curve",std::vector<float>());

      a_score = GetBenchmarkPercentage("lowest_a-")*p_score;
      b_score = GetBenchmarkPercentage("lowest_b-")*p_score;
      c_score = GetBenchmarkPercentage("lowest_c-")*p_score;
      d_score = GetBenchmarkPercentage("lowest_d")*p_score;

      if (!curve.empty()) {
        assert(curve.size() == benchmark_percents.size());
        for (std::size_t x = 0; x < benchmark_percents.size(); x++) {
          if (benchmark_percents[x] == "lowest_a-") a_score = curve[x];
          if (benchmark_percents[x] == "lowest_b-") b_score = curve[x];
          if (benchmark_percents[x] == "lowest_c-") c_score = curve[x];
          if (benchmark_percents[x] == "lowest_d") d_score = curve[x];
        }
      }

      c_score = std::min(b_score,c_score);
      d_score = std::min(c_score,d_score);

      bool released = grade_id.value("released",true);
      GRADEABLES[g].setReleased(token_key,released);

      float maximum = grade_id.value("max",0);
      GRADEABLES[g].setMaximum(token_key,maximum);

      float clamp = grade_id.value("clamp",-1);
      GRADEABLES[g].setClamp(token_key,clamp);
      
      //std::cout << "scores " << p_score << " "<< a_score << " " << b_score <<  " " << c_score << " " << d_score << std::endl;

      assert (p_score >= a_score &&
              a_score >= b_score &&
              b_score >= c_score &&
              c_score >= d_score);
      
      assert (which >= 0 && which < GRADEABLES[g].getCount());
      if (GRADEABLES[g].isReleased(token_key)) {
        perfect->setGradeableItemGrade(g,which, p_score);
        lowest_a->setGradeableItemGrade(g,which, a_score);
        lowest_b->setGradeableItemGrade(g,which, b_score);
        lowest_c->setGradeableItemGrade(g,which, c_score);
        lowest_d->setGradeableItemGrade(g,which, d_score);
      }
    
    //std::cout << "it makes it to exam data" << std::endl;
    
    nlohmann::json exam_data = grade_id["exam_data"];
    //std::cout << exam_data << std::endl;
    if (!exam_data.empty()) {
    //std::cout << "makes it into exam data" << std::endl;
      int active = exam_data["active"].get<int>();
    if (active == 1) {

                  GLOBAL_ACTIVE_TEST_ZONE = k;

        for (nlohmann::json::iterator itr2 = (exam_data).begin(); itr2 != (exam_data).end(); itr2++) {
          std::string token2 = itr2.key();
        //std::cout << token2 << std::endl;
        if (token2 == "exam_title") {
          std::string value = itr2.value();
          GLOBAL_EXAM_TITLE = value;
        } else if (token2 == "exam_date") {
          std::string value = itr2.value();
          GLOBAL_EXAM_DATE = value;
        } else if (token2 == "exam_time") {
          std::string value = itr2.value();
          GLOBAL_EXAM_TIME = value;
        } else if (token2 == "exam_default_room") {
          std::string value = itr2.value();
          GLOBAL_EXAM_DEFAULT_ROOM = value;
        } else if (token2 == "min_overall_for_zone_assignment") {
          float value = itr2.value();
          GLOBAL_MIN_OVERALL_FOR_ZONE_ASSIGNMENT = value;
        } else if (token2 == "exam_seating") {
          std::cout << "TOKEN IS EXAM SEATING" << std::endl;
          std::string value = itr2.value();
          GLOBAL_EXAM_SEATING = value;
        } else if (token2 == "seating_spacing") {
          std::cout << "TOKEN IS SEATING SPACING" << std::endl;
          std::string value = itr2.value();
          GLOBAL_SEATING_SPACING = value;
        } else if (token2 == "exam_seating_count") {
          std::cout << "TOKEN IS EXAM SEATING COUNT" << std::endl;
          std::string value = itr2.value();
          GLOBAL_EXAM_SEATING_COUNT = value;
        } else if (token2 == "left_right_handedness") {
          std::cout << "TOKEN IS LEFT RIGHT HANDEDNESS" << std::endl;
          std::string value = itr2.value();
          GLOBAL_LEFT_RIGHT_HANDEDNESS = value;
        }
        }
    }
    }
    }
  }
  students.push_back(perfect);
  students.push_back(student_average);
  students.push_back(student_stddev);
  students.push_back(lowest_a);
  students.push_back(lowest_b);
  students.push_back(lowest_c);
  students.push_back(lowest_d);
  
  //std::cout << "3" << std::endl;
  
  // get display optioons
  std::vector<std::string> displays = j["display"].get<std::vector<std::string> >();
  for (std::size_t i=0; i<displays.size(); i++) {
  token = displays[i];
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
  }
  
  //std::cout << "4" << std::endl;
  
  // Set Display Benchmark
  std::vector<std::string> displayBenchmark = j["display_benchmark"].get<std::vector<std::string> >();
  for (std::size_t i=0; i<displayBenchmark.size(); i++) {
    token = displayBenchmark[i];
  DisplayBenchmark(token);
  }
  
  //std::cout << "5" << std::endl;
  
  
  //std::cout << "6" << std::endl;
  
  // Set Benchmark Color
  nlohmann::json benchmarkColor = j["benchmark_color"];
  for (nlohmann::json::iterator itr = benchmarkColor.begin(); itr != benchmarkColor.end(); itr++) {
    token = itr.key();
  std::string color;
  color = itr.value();
  
  SetBenchmarkColor(token,color);
  }
  
  //std::cout << "7" << std::endl;

  //std::cout << "8" << std::endl;
  
  nlohmann::json useJSON = j["use"];
  for (nlohmann::json::iterator itr = useJSON.begin(); itr != useJSON.end(); itr++) {
  token = itr.key();
  if (token == "late_day_penalty") {
    LATE_DAY_PERCENTAGE_PENALTY = itr.value();
    assert (LATE_DAY_PERCENTAGE_PENALTY >= 0.0 && LATE_DAY_PERCENTAGE_PENALTY <= 0.5);
  } else if (token == "test_improvement_averaging_adjustment") {
    TEST_IMPROVEMENT_AVERAGING_ADJUSTMENT = true;
  } else if (token == "quiz_normalize_and_drop") {
    QUIZ_NORMALIZE_AND_DROP = itr.value();
  } else if (token == "lowest_test_counts_half") {
    LOWEST_TEST_COUNTS_HALF = true;
  } else {
    std::cout << "ERROR: unknown use " << token << std::endl;
      exit(0);
  }
  }
  
  //std::cout << "9" << std::endl;
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
      << std::left << std::setw(13) << students[i]->getPreferredName() 
      << std::left << std::setw(12) << students[i]->getUserName()
      << std::left << std::setw(12) << room
      << std::left << std::setw(10) << zone
      << std::endl;

    ostr 
      << students[i]->getLastName() << ","
      << students[i]->getPreferredName() << ","
      << students[i]->getUserName() << std::endl;

#else

    ostr 
      << students[i]->getSection()   << "\t"
      << students[i]->getLastName()     << "\t"
      << students[i]->getPreferredName() << "\t"
      << students[i]->getUserName()  << "\t"
      //<< foo 
      << std::endl;
 
#endif
  }

}


// defined in zone.cpp
void LoadExamSeatingFile(const std::string &zone_counts_filename,
                         const std::string &zone_assignments_filename,
                         const std::string &seating_spacing,
                         const std::string &left_right_handedness,
                         std::vector<Student*> &students);

void load_student_grades(std::vector<Student*> &students);

void load_bonus_late_day(std::vector<Student*> &students, int which_lecture, std::string bonus_late_day_file);

void processcustomizationfile(std::vector<Student*> &students) {


  std::ifstream istr(CUSTOMIZATION_FILE.c_str());
  assert (istr);
  nlohmann::json j;
  j << istr;
  GLOBAL_CUSTOMIZATION_JSON = j;


  SetBenchmarkPercentage("lowest_a-",0.9);
  SetBenchmarkPercentage("lowest_b-",0.8);
  SetBenchmarkPercentage("lowest_c-",0.7);
  SetBenchmarkPercentage("lowest_d",0.6);

  SetBenchmarkColor("extracredit","aa88ff"); // dark purple
  SetBenchmarkColor("perfect"    ,"c8c8ff"); // purple
  SetBenchmarkColor("lowest_a-"  ,"c8ffc8"); // green
  SetBenchmarkColor("lowest_b-"  ,"ffffc8"); // yellow
  SetBenchmarkColor("lowest_c-"  ,"ffc8c8"); // orange
  SetBenchmarkColor("lowest_d"   ,"ff0000"); // red
  SetBenchmarkColor("failing"    ,"c80000"); // dark red
  
  //std::cout << "it makes it here!!" << std::endl;

  preprocesscustomizationfile(students);
  
  //std::cout << "it makes it here" << std::endl;
  
  load_student_grades(students);

  /*
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
      //assert ( GLOBAL_recommend_id == "");
      istr >> GLOBAL_recommend_id;

      // / *
      std::string username;
      istr >> username;
      char line[MAX_STRING_LENGTH];
      istr.getline(line,MAX_STRING_LENGTH);
      Student *s = GetStudent(students,username);
      if (students_loaded == false) continue;
      assert (s != NULL);
      s->addRecommendation(line);
      // * / / *

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

    } else if (token == "earned_late_days") {
      DISPLAY_LATE_DAYS = true;
      char line[MAX_STRING_LENGTH];
      istr.getline(line,MAX_STRING_LENGTH);
      GLOBAL_earned_late_days.clear();
      std::stringstream ss(line);
      float tmp;
      while (ss >> tmp) {
        assert (GLOBAL_earned_late_days.size() == 0 || tmp > GLOBAL_earned_late_days.back());
        GLOBAL_earned_late_days.push_back(tmp);
      }

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


    } else if (token == "display" ||
               token == "display_benchmark" ||
               token == "benchmark_percent") {

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
            g == GRADEABLE_ENUM::ASSIGNMENT ||      
            g == GRADEABLE_ENUM::PROBLEM_SET ||     
            g == GRADEABLE_ENUM::QUIZ ||            
            g == GRADEABLE_ENUM::TEST ||            
            g == GRADEABLE_ENUM::EXAM ||            
            g == GRADEABLE_ENUM::EXERCISE ||        
            g == GRADEABLE_ENUM::LECTURE_EXERCISE ||
            g == GRADEABLE_ENUM::READING ||         
            g == GRADEABLE_ENUM::LAB ||             
            g == GRADEABLE_ENUM::RECITATION ||      
            g == GRADEABLE_ENUM::PROJECT ||         
            g == GRADEABLE_ENUM::PARTICIPATION) {

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

        a_score = GetBenchmarkPercentage("lowest_a-")*p_score;
        b_score = GetBenchmarkPercentage("lowest_b-")*p_score;
        c_score = GetBenchmarkPercentage("lowest_c-")*p_score;
        d_score = GetBenchmarkPercentage("lowest_d")*p_score;
        
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

    students.push_back(perfect);
    students.push_back(student_average);
    students.push_back(student_stddev);
    students.push_back(lowest_a);
    students.push_back(lowest_b);
    students.push_back(lowest_c);
    students.push_back(lowest_d);

  } else {
    MatchClickerRemotes(students, iclicker_remotes_filename);
    AddClickerScores(students,iclicker_questions);
  }
  */
  
  //std::ifstream istr(CUSTOMIZATION_FILE.c_str());
  //assert (istr);

  std::string token,token2;
  //int num;
  //int which;
  //std::string which_token;
  //float p_score,a_score,b_score,c_score,d_score;

  std::string iclicker_remotes_filename;
  std::vector<std::vector<iClickerQuestion> > iclicker_questions(MAX_LECTURES+1);

  
  for (nlohmann::json::iterator itr = j.begin(); itr != j.end(); itr++) {
    token = itr.key();
    //std::cout << token << std::endl;
  if (token == "section") {
    // create sections
    int counter = 0;
    for (nlohmann::json::iterator itr2 = (itr.value()).begin(); itr2 != (itr.value()).end(); itr2++) {
    std::string temp = itr2.key();
    int section = std::stoi(temp);
    std::string section_name = itr2.value();
    std::cout << "MAKE ASSOCIATION " << section << " " << section_name << std::endl;
    //assert (!validSection(section));
    sectionNames[section] = section_name;
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
  } else if (token == "messages") {
    // general message at the top of the file
    for (nlohmann::json::iterator itr2 = (itr.value()).begin(); itr2 != (itr.value()).end(); itr2++) {
            MESSAGES.push_back(*itr2);
    }
  } else if (token == "warning") {
    // EWS early warning system [ per student ]
    std::vector<nlohmann::json> warning_list = j[token].get<std::vector<nlohmann::json> >();
    for (std::size_t i = 0; i<warning_list.size(); i++) {
            nlohmann::json warning = warning_list[i];
      //std::string username = warning_user["user"].get<std::string>();
            std::string message = warning["msg"].get<std::string>();
            std::vector<std::string> ids;
            float value = warning["value"].get<float>();
            nlohmann::json j_ids = warning["ids"];
            for (std::size_t k = 0; k < j_ids.size(); k++) {
              ids.push_back(j_ids[k].get<std::string>());
            }

            std::cout << "search for " << message << std::endl;
            for (std::size_t S = 0; S < students.size(); S++) {
              Student *s = students[S];
              if (!validSection(students[S]->getSection())) continue;
              //std::cout << "student " << s->getUserName() << std::endl;
              float v = 0;
              for (std::size_t k = 0; k < ids.size(); k++) {
                GRADEABLE_ENUM g;
                int item;
                //std::cout << "GRADEABLE_ENUM " << (int)g << " " << item << std::endl;
                LookupGradeable(ids[k],g,item);
                v += s->getGradeableItemGrade(g,item).getValue();
              }

              if (v < value) {
                std::stringstream ss;
                ss << " " << v << " < " << value;

                s->addWarning(message + ss.str());
              }
            }

    }

  } else if (token == "recommend") {
    // UTA/mentor recommendations [ per student ]
    std::vector<nlohmann::json> recommend_list = j[token].get<std::vector<nlohmann::json> >();
    for (std::size_t i = 0; i < recommend_list.size(); i++) {
    nlohmann::json recommend_user = recommend_list[i];
      std::string username = recommend_user["user"].get<std::string>();
    std::string message = recommend_user["msg"].get<std::string>();
    Student *s = GetStudent(students,username);
    if (s == NULL) {
          std::cout << username << std::endl;
        }
        assert (s != NULL);
    s->addRecommendation(message);
    }
  } else if (token == "note") {
    // other grading note [ per student ]
    std::vector<nlohmann::json> note_list = j[token].get<std::vector<nlohmann::json> >();
    for (std::size_t i = 0; i < note_list.size(); i++) {
    nlohmann::json note_user = note_list[i];
    std::string username = note_user["user"].get<std::string>();
    std::string message = note_user["msg"].get<std::string>();
    Student *s = GetStudent(students,username);
    if (s == NULL) {
          std::cout << username << std::endl;
        }
        assert (s != NULL);
    s->addNote(message);
    }
  } else if (token == "earned_late_days") {
    DISPLAY_LATE_DAYS = true;
    std::vector<float> earnedLateDays = j[token].get<std::vector<float> >();
    GLOBAL_earned_late_days.clear();
    for (std::size_t i = 0; i < earnedLateDays.size(); i++) {
      float tmp = earnedLateDays[i];
    assert (GLOBAL_earned_late_days.size() == 0 || tmp > GLOBAL_earned_late_days.back());
        GLOBAL_earned_late_days.push_back(tmp);
    }
  } else if (token == "iclicker_ids") {
    iclicker_remotes_filename = itr.value();
  } else if (token == "iclicker") {
    for (nlohmann::json::iterator itr2 = (itr.value()).begin(); itr2 != (itr.value()).end(); itr2++) {
      std::string temp = itr2.key();
    std::vector<nlohmann::json> iclickerLectures = j[token][temp];
    for (std::size_t i = 0; i < iclickerLectures.size(); i++) {
      nlohmann::json iclickerLecture = iclickerLectures[i];
      int which_lecture = std::stoi(temp);
      std::string clicker_file = iclickerLecture["file"].get<std::string>();
      int which_column = iclickerLecture["column"].get<int>();
      std::string correct_answer = iclickerLecture["answer"].get<std::string>();
      assert (which_lecture >= 1 && which_lecture <= MAX_LECTURES);
      iclicker_questions[which_lecture].push_back(iClickerQuestion(clicker_file,which_column,correct_answer));
    }
    }
  } else if (token == "audit") {
    std::vector<nlohmann::json> audit_list = itr.value();
    for (std::size_t i = 0; i < audit_list.size(); i++) {
      std::string username = audit_list[i];
        Student *s = GetStudent(students,username);
        assert (s != NULL);
        assert (s->getAudit() == false);
        s->setAudit();
        s->addNote("AUDIT");
    }
  } else if (token == "withdraw") {
    std::vector<nlohmann::json> withdraw_list = itr.value();
    for (std::size_t i = 0; i < withdraw_list.size(); i++) {
      std::string username = withdraw_list[i];
        Student *s = GetStudent(students,username);
        assert (s != NULL);
        assert (s->getWithdraw() == false);
        s->setWithdraw();
        s->addNote("LATE WITHDRAW");
    }
  } else if (token == "independentstudy") {
    std::vector<nlohmann::json> independent_study_list = itr.value();
    for (std::size_t i = 0; i < independent_study_list.size(); i++) {
      std::string username = independent_study_list[i];
        Student *s = GetStudent(students,username);
        assert (s != NULL);
        assert (s->getIndependentStudy() == false);
        s->setIndependentStudy();
        s->addNote("INDEPENDENT STUDY");
    }
  } else if (token == "manual_grade") {
    for (nlohmann::json::iterator itr2 = (itr.value()).begin(); itr2 != (itr.value()).end(); itr2++) {
      std::string username = itr2.key();
    std::string grade = (itr2.value())["grade"].get<std::string>();
    std::string note = (itr2.value())["note"].get<std::string>();
    Student *s = GetStudent(students,username);
        assert (s != NULL);
        s->ManualGrade(grade,note);
    }
  } else if (token == "plagiarism") {
    for (nlohmann::json::iterator itr2 = (itr.value()).begin(); itr2 != (itr.value()).end(); itr2++) {
      std::string username = (itr2.value())["user"].get<std::string>();
      std::string hw = (itr2.value())["gradeable"].get<std::string>();
      float penalty = (itr2.value())["penalty"].get<float>();
      //assert (hw >= 1 && hw <= 10);
      assert (penalty >= -0.01 && penalty <= 1.01);
      Student *s = GetStudent(students,username);
      assert (s != NULL);
      s->mossify(hw,penalty);
    }
  } else if (token == "final_cutoff") {
    for (nlohmann::json::iterator itr2 = (itr.value()).begin(); itr2 != (itr.value()).end(); itr2++) {
      std::string grade = itr2.key();
    float cutoff = itr2.value();
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
    }
  } else if (token == "gradeables") {
    continue;
  } else if (token == "bonus_latedays") {
    nlohmann::json bonusJson = j[token];
    for (nlohmann::json::iterator itr2 = bonusJson.begin(); itr2 != bonusJson.end(); itr2++) {
      std::string bonus = itr2.key();
      BONUS_WHICH_LECTURE = std::stoi(bonus);
      BONUS_FILE = j[token][bonus];
        std::cout << "BONUS LATE DAYS" << std::endl;
      
      if (BONUS_FILE != "") {
          load_bonus_late_day(students,BONUS_WHICH_LECTURE,BONUS_FILE);
      }
    }
  } else {
    if (token == "display" || token == "display_benchmark" || token == "benchmark_percent") {
      continue;
  } else if (token == "use" || token == "hackmaxprojects" || token == "benchmark_color") {
    continue;
  }
    std::cout << "ERROR: UNKNOWN TOKEN  X " << token << std::endl;
  }
  }
  
  if (GLOBAL_EXAM_SEATING_COUNT != "" && GLOBAL_EXAM_SEATING != "") {
    LoadExamSeatingFile(GLOBAL_EXAM_SEATING_COUNT,GLOBAL_EXAM_SEATING,GLOBAL_SEATING_SPACING,GLOBAL_LEFT_RIGHT_HANDEDNESS,students);
  }
  MakeRosterFile(students);
  MatchClickerRemotes(students, iclicker_remotes_filename);
  AddClickerScores(students,iclicker_questions);
}



void load_student_grades(std::vector<Student*> &students) {

  Student *perfect = GetStudent(students,"PERFECT");
  assert (perfect != NULL);

  Student *student_average = GetStudent(students,"AVERAGE");
  assert (student_average != NULL);

  Student *student_stddev = GetStudent(students,"STDDEV");
  assert (student_stddev != NULL);

  
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
  
  nlohmann::json j;
  j << istr;

  std::ifstream customization_istr(CUSTOMIZATION_FILE.c_str());
  assert (customization_istr);
  nlohmann::json customization_j;
  customization_j << customization_istr;

  std::string participation_gradeable_id = "";
  std::string participation_component = "";
  std::string understanding_gradeable_id = "";
  std::string understanding_component = "";
  std::string recommendation_gradeable_id = "";
  std::string recommendation_text = "";

  if (customization_j.find("participation") != customization_j.end()) {
    participation_gradeable_id = customization_j["participation"]["id"].get<std::string>();
    participation_component = customization_j["participation"]["component"].get<std::string>();
  }
  if (customization_j.find("understanding") != customization_j.end()) {
    understanding_gradeable_id = customization_j["understanding"]["id"].get<std::string>();
    understanding_component = customization_j["understanding"]["component"].get<std::string>();
  }
  if (customization_j.find("recommendation") != customization_j.end()) {
    recommendation_gradeable_id = customization_j["recommendation"]["id"].get<std::string>();
    recommendation_text = customization_j["recommendation"]["text"].get<std::string>();
  }

  for (nlohmann::json::iterator itr = j.begin(); itr != j.end(); itr++) {
    std::string token = itr.key();
    // std::cout << "token: " << token << "!" << std::endl;
    GRADEABLE_ENUM g;
    bool gradeable_enum_success = string_to_gradeable_enum(token,g);
          if (!gradeable_enum_success && token != "Other" && token != "rubric" && token != "Test") {
    // non gradeables
    if (token == "user_id") {
      s->setUserName(j[token].get<std::string>());
    } else if (token == "legal_first_name") {
      s->setLegalFirstName(j[token].get<std::string>());
    } else if (token == "preferred_first_name") {
                  if (!j[token].is_null()) {
                    s->setPreferredFirstName(j[token].get<std::string>());
                  }
                } else if (token == "last_name") {
      s->setLastName(j[token].get<std::string>());
    } else if (token == "last_update") {
      s->setLastUpdate(j[token].get<std::string>());
    } else if (token == "registration_section") {
          int a;
          if(!j[token].is_null()) {
            a = j[token].get<int>();
            if (!validSection(a)) {
              // the "drop" section is 0 (really should be NULL)
              if (a != 0) {
                std::cerr << "WARNING: invalid section " << a << std::endl;
              }
            }
          }
          else{
            a = 0;
          }
          s->setSection(a);

    } else if (token == "default_allowed_late_days") {
                  int value = 0;
                  if (!j[token].is_null()) {
                    if (j[token].is_string()) {
                      std::string s_value = j[token].get<std::string>();
                      value = std::stoi(s_value);
                    } else {
                      value = j[token].get<int>();
                    }
                  }
                  s->setDefaultAllowedLateDays(value);
                  if (value > 0) {
                    DISPLAY_LATE_DAYS = true;
                  }
                } else {
            std::cout << "UNKNOWN TOKEN Y '" << token << "'" << std::endl;
      exit(0);
    }
    } else {
      for (nlohmann::json::iterator itr2 = (itr.value()).begin(); itr2 != (itr.value()).end(); itr2++) {
      int which;
      bool invalid = false;
      std::string gradeable_id = (*itr2).value("id","ERROR BAD ID");
      std::string gradeable_name = (*itr2).value("name",gradeable_id);
      std::string status;
      if (itr2 != (itr.value()).end() && (*itr2).is_string()) {
        status = (*itr2).value("status","NOT ELECTRONIC");
      } else {
        status = "NO SUBMISSION";
      }
      float score = (*itr2).value("score",0.0);
                  if (status.find("Bad") != std::string::npos) {
                    assert (score == 0);
                  } else {
                      assert (status == "NO SUBMISSION" || status == "NOT ELECTRONIC" || status == "Good" || status == "Late");
                  }

                  std::string other_note = "";
                  //                  nlohmann::json obj = (*itr2).value("text",nlohmann::json::object());
                  nlohmann::json::iterator itr3 = itr2->find("text");
                  if (itr3 != itr2->end()) {
                    for (std::size_t i = 0; i < itr3->size(); i++) {
                      other_note += (*itr3)[i].value("Notes","");
                    }  
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
      
                  if (gradeable_id == GLOBAL_recommend_id) {
                    nlohmann::json::iterator rec = itr2->find("text");
                    if (rec != itr2->end()) {
                      for (std::size_t i = 0; i < (*rec).size(); i++) {
                        for (auto itr8 = (*rec)[i].begin(); itr8 != (*rec)[i].end(); itr8++) {
                          std::string r = itr8.value();
                          s->addRecommendation(std::string(" ") + r);
                        }
                      }
                    }
                  }

      if (!invalid) {
      assert (which >= 0);
      assert (score >= 0.0);
                        int late_days_charged = itr2->value("days_charged",0);
                        if (itr3 != itr2->end()) {
                          if (score <= 0) {
                            if (s->getUserName() != "") {
                              assert (late_days_charged == 0);
                            }
                          }
                        }
                        float clamp = -1;
                        clamp = GRADEABLES[g].getClamp(gradeable_id);
                        if (clamp > 0) {
                          score = std::min(clamp,score);
                        }
                        if (status.find("Bad") != std::string::npos) {
                          assert (late_days_charged == 0);
                        }
                        if (GRADEABLES[g].isReleased(gradeable_id)) {
                          s->setGradeableItemGrade(g,which,score,late_days_charged,other_note,status);
                        }
      }

      }  
    }
  }

  float participation = 0;
  float understanding = 0;
  std::string recommendation;
  if (participation_gradeable_id != "") {
    std::vector<nlohmann::json> notes = j["Note"];
    for (int x = 0; x < notes.size(); x++) {
      if (notes[x]["id"] == participation_gradeable_id) {
        nlohmann::json scores = notes[x]["component_scores"];
        for (int y = 0; y < scores.size(); y++) {
          if (scores[y].find(participation_component) != scores[y].end()) {
            participation = scores[y][participation_component].get<float>();
          }
        }
      }
    }
  }
  if (understanding_gradeable_id != "") {
    std::vector<nlohmann::json> notes = j["Note"];
    for (int x = 0; x < notes.size(); x++) {
      if (notes[x]["id"] == understanding_gradeable_id) {
        nlohmann::json scores = notes[x]["component_scores"];
        for (int y = 0; y < scores.size(); y++) {
          if (scores[y].find(understanding_component) != scores[y].end()) {
            understanding = scores[y][understanding_component].get<float>();
          }
        }
      }
    }
  }
  if (recommendation_gradeable_id != "") {
    std::vector<nlohmann::json> notes = j["Note"];
    for (int x = 0; x < notes.size(); x++) {
      if (notes[x]["id"] == recommendation_gradeable_id) {
        nlohmann::json values = notes[x]["text"];
        for (int y = 0; y < values.size(); y++) {
          if (values[y].find(recommendation_text) != values[y].end()) {
            recommendation = values[y][recommendation_text].get<std::string>();
          }
        }
      }
    }
  }

  s->setParticipation(participation);
  s->setUnderstanding(understanding);
  if (recommendation != "") {
    s->addRecommendation(recommendation);
  }
  students.push_back(s);
  }

}


void start_table_open_file(bool full_details, 
                 const std::vector<Student*> &students, int S, int month, int day, int year,
                 enum GRADEABLE_ENUM which_gradeable_enum);

void start_table_output(bool full_details,
                        const std::vector<Student*> &students, int S, int month, int day, int year,
                        Student *sp, Student *sa, Student *sb, Student *sc, Student *sd);

void end_table(std::ofstream &ostr,  bool full_details, Student *s);



// =============================================================================================
// =============================================================================================
// =============================================================================================



void output_helper(std::vector<Student*> &students,  std::string &GLOBAL_sort_order) {

  Student *sp = GetStudent(students,"PERFECT");
  Student *student_average = GetStudent(students,"AVERAGE");
  Student *student_stddev = GetStudent(students,"STDDEV");
  Student *sa = GetStudent(students,"LOWEST A-");
  Student *sb = GetStudent(students,"LOWEST B-");
  Student *sc = GetStudent(students,"LOWEST C-");
  Student *sd = GetStudent(students,"LOWEST D");
  assert (sp != NULL);
  assert (student_average != NULL);
  assert (student_stddev != NULL);
  assert (sa != NULL);
  assert (sb != NULL);
  assert (sc != NULL);
  assert (sd != NULL);

  std::string command = "rm -f " + OUTPUT_FILE + " " + INDIVIDUAL_FILES_OUTPUT_DIRECTORY + "*html";
  system(command.c_str());
  command = "rm -f " + OUTPUT_FILE + " " + INDIVIDUAL_FILES_OUTPUT_DIRECTORY + "*json";
  system(command.c_str());

  // get todays date;
  time_t now = time(0);  
  struct tm * now2 = localtime( & now );
  int month = now2->tm_mon+1;
  int day = now2->tm_mday;
  int year = now2->tm_year+1900;

  start_table_open_file(true,students,-1,month,day,year,GRADEABLE_ENUM::NONE);
  start_table_output(true,students,-1,month,day,year, sp,sa,sb,sc,sd);

  int next_rank = 1;
  int last_section = -1;

  for (int S = 0; S < (int)students.size(); S++) {
    //int rank = next_rank;
    if (students[S] == sp ||
        students[S] == student_average ||
        students[S] == student_stddev ||
        students[S] == sa ||
        students[S] == sb ||
        students[S] == sc ||
        students[S] == sd ||
        //        students[S]->getUserName() == "" ||
        !validSection(students[S]->getSection())) {
      //rank = -1;
    } else {
      if (GLOBAL_sort_order == std::string("by_section") &&
          last_section != students[S]->getSection()) {
        last_section = students[S]->getSection();
        //next_rank = rank = 1;
        next_rank = 1;
      }
      next_rank++;
    }
    Student *this_student = students[S];
    assert (this_student != NULL);
  }
  

  for (int S = 0; S < (int)students.size(); S++) {

    nlohmann::json mj;

    std::string file2 = INDIVIDUAL_FILES_OUTPUT_DIRECTORY + students[S]->getUserName() + "_message.html";
    std::string file2_json = INDIVIDUAL_FILES_OUTPUT_DIRECTORY + students[S]->getUserName() + "_message.json";
    std::ofstream ostr2(file2.c_str());
    std::ofstream ostr2_json(file2_json.c_str());

    mj["username"] = students[S]->getUserName();
    mj["building"] = "DCC";
    mj["room"] = "308";
    mj["zone"] = "A";

    ostr2_json << mj.dump(4);
    

#if 0
    if (students[S]->hasPriorityHelpStatus()) {
      ostr2 << "<h3>PRIORITY HELP QUEUE</h3>" << std::endl;
      priority_stream << std::left << std::setw(15) << students[S]->getSection()
                      << std::left << std::setw(15) << students[S]->getUserName() 
                      << std::left << std::setw(15) << students[S]->getPreferredName() 
                      << std::left << std::setw(15) << students[S]->getLastName() << std::endl;
      
      
    }
    
    if (MAX_ICLICKER_TOTAL > 0) {
      ostr2 << "<em>recent iclicker = " << students[S]->getIClickerRecent() << " / 12.0</em>" << std::endl;
    }
#endif


    nlohmann::json::iterator special_message_itr = GLOBAL_CUSTOMIZATION_JSON.find("special_message");
    nlohmann::json special_message;
    if (special_message_itr != GLOBAL_CUSTOMIZATION_JSON.end()) {
      special_message = *special_message_itr;
    }

    PrintExamRoomAndZoneTable(ostr2,students[S],special_message);

    int prev = students[S]->getAllowedLateDays(0);

    for (int i = 1; i <= MAX_LECTURES; i++) {
      int tmp = students[S]->getAllowedLateDays(i);
      if (prev != tmp) {

        std::map<int,Date>::iterator itr = LECTURE_DATE_CORRESPONDENCES.find(i);
        if (itr == LECTURE_DATE_CORRESPONDENCES.end()) {
          continue;
        }
        Date &d = itr->second;
        late_days_stream << students[S]->getUserName() << ","
                         << d.getStringRep() << "," 
                         << tmp << std::endl;
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

  std::cout << "LOAD BONUS LATE" << std::endl;

  std::ifstream istr(bonus_late_day_file.c_str());
  if (!istr.good()) {
    std::cerr << "ERROR!  could not open " << bonus_late_day_file << std::endl;
    exit(1);
  }

  std::string username;
  while (istr >> username) {
    Student *s = GetStudent(students,username);
    if (s == NULL) {
      //std::cerr << "ERROR!  bad username " << username << " cannot give bonus late day " << std::endl;
      //exit(1);
    } else {
      //std::cout << "BONUS DAY FOR USER " << username << std::endl;
      s->add_bonus_late_day(which_lecture);
      //std::cout << "add bonus late day for " << username << std::endl;
    }
  } 

}



int main(int argc, char* argv[]) {

  //std::string sort_order = "by_overall";

  if (argc > 1) {
    assert (argc == 2);
    GLOBAL_sort_order = argv[1];
  }
  
  std::vector<Student*> students;  
  processcustomizationfile(students);
  //processcustomizationfile(students,false);

  // ======================================================================
  // LOAD ALL THE STUDENT DATA
  //load_student_grades(students);


  /*
  std::cout << "LOAD BONUS " << BONUS_FILE << std::endl;
  if (BONUS_FILE != "") {
    load_bonus_late_day(students,BONUS_WHICH_LECTURE,BONUS_FILE);
  }
  */

  // ======================================================================
  // MAKE BENCHMARK STUDENTS FOR THE CURVES
  //processcustomizationfile(students,true); 


  // ======================================================================
  // SUGGEST CURVES

  suggest_curves(students);


  // ======================================================================
  // SORT
  std::sort(students.begin(),students.end(),by_overall);


  if (GLOBAL_sort_order == std::string("by_overall")) {
    std::sort(students.begin(),students.end(),by_overall);
  } else if (GLOBAL_sort_order == std::string("by_name")) {
    std::sort(students.begin(),students.end(),by_name);
  } else if (GLOBAL_sort_order == std::string("by_section")) {
    std::sort(students.begin(),students.end(),by_section);
  } else if (GLOBAL_sort_order == std::string("by_zone")) {

    DISPLAY_INSTRUCTOR_NOTES = false;
    DISPLAY_EXAM_SEATING = true;
    DISPLAY_MOSS_DETAILS = false;
    DISPLAY_FINAL_GRADE = false;
    DISPLAY_GRADE_SUMMARY = false;
    DISPLAY_GRADE_DETAILS = false;
    DISPLAY_ICLICKER = false;

    std::sort(students.begin(),students.end(),by_name);

  } else if (GLOBAL_sort_order == std::string("by_iclicker")) {
    std::sort(students.begin(),students.end(),by_iclicker);

    DISPLAY_ICLICKER = true;

  } else if (GLOBAL_sort_order == std::string("by_test_and_exam")) {
    std::sort(students.begin(),students.end(),by_test_and_exam);

  } else {
    assert (GLOBAL_sort_order.size() > 3);
    GRADEABLE_ENUM g;
    // take off "by_"
    std::string tmp = GLOBAL_sort_order.substr(3,GLOBAL_sort_order.size()-3);
    bool success = string_to_gradeable_enum(tmp,g);
    if (success) {
      std::sort(students.begin(),students.end(), GradeableSorter(g) );
    }
    else {
      std::cerr << "UNKNOWN SORT OPTION " << GLOBAL_sort_order << std::endl;
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

  output_helper(students,GLOBAL_sort_order);

}


void suggest_curves(std::vector<Student*> &students) {

  Student *student_average  = GetStudent(students,"AVERAGE");
  Student *student_stddev  = GetStudent(students,"STDDEV");

  for (unsigned int i = 0; i < ALL_GRADEABLES.size(); i++) {
    GRADEABLE_ENUM g = ALL_GRADEABLES[i];

    for (int item = 0; item < GRADEABLES[g].getCount(); item++) {
      
      std::string gradeable_id = GRADEABLES[g].getID(item);
      if (gradeable_id == "") continue;
      
      const std::string& gradeable_name = GRADEABLES[g].getCorrespondence(gradeable_id).second;
      
      std::cout << gradeable_to_string(g) << " " << gradeable_id << " " << gradeable_name/* << " statistics & suggested curve"*/ << std::endl;
      std::vector<float> scores;
      
      std::map<int, int> section_counts;
      
      for (unsigned int S = 0; S < students.size(); S++) {
        if (students[S]->getSection() > 0 && students[S]->getGradeableItemGrade(g,item).getValue() > 0) {
          scores.push_back(students[S]->getGradeableItemGrade(g,item).getValue());
          section_counts[students[S]->getSection()]++;
        }
      }
      if (scores.size() > 0) {
        //std::cout << "   " << scores.size() << " submitted" << std::endl;
        std::sort(scores.begin(),scores.end());
        float sum = 0;
        for (unsigned int x = 0; x < scores.size(); x++) {
          sum+=scores[x];
        }
        float average = sum / float(scores.size());
        std::cout << "    average=" << std::setprecision(2) << std::fixed << average;

        student_average->setGradeableItemGrade(g,item,average);

        sum = 0;
        for (unsigned int x = 0; x < scores.size(); x++) {
          sum+=(average-scores[x])*(average-scores[x]);
        }
        float stddev = sqrt(sum/float(scores.size()));
        std::cout << "    stddev=" << std::setprecision(2) << std::fixed << stddev;
        student_stddev->setGradeableItemGrade(g,item,stddev);

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

