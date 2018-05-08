#include "student.h"

const std::string GradeColor(const std::string &grade);

// =============================================================================================
// =============================================================================================
// CONSTRUCTOR

Student::Student() { 

  // personal data
  // (defaults to empty string)
  lefty = false;
  
  // registration status
  section = 0;  
  audit = false;
  withdraw = false;
  independentstudy = false;

  default_allowed_late_days = 0;
  current_allowed_late_days = 0;

  // grade data
  for (unsigned int i = 0; i < ALL_GRADEABLES.size(); i++) { 
    GRADEABLE_ENUM g = ALL_GRADEABLES[i];
    all_item_grades[g]   = std::vector<ItemGrade>(GRADEABLES[g].getCount(),ItemGrade(0));
  }
  // (iclicker defaults to empty map)

  zones = std::vector<std::string>(GRADEABLES[GRADEABLE_ENUM::TEST].getCount(),"");
  moss_penalty = 0;
  cached_hw = -1;

  // other grade-like data
  // (remote id defaults to empty vector)
  academic_integrity_form = false;
  participation = 0;
  understanding = 0;

  // info about exam assignments
  // (defaults to empty string)

  // per student notes
  // (defaults to empty string)
}




// lookup a student by username
Student* GetStudent(const std::vector<Student*> &students, const std::string& username) {
  for (unsigned int i = 0; i < students.size(); i++) {
    if (students[i]->getUserName() == username) return students[i];
  }
  return NULL;
}





// =============================================================================================
// =============================================================================================
// accessor & modifier for grade data

const ItemGrade& Student::getGradeableItemGrade(GRADEABLE_ENUM g, int i) const {
  static ItemGrade emptyItemGrade(0);
  //std::cout << "i " << i << "   count " << GRADEABLES[g].getCount() << std::endl;
  if (i >= GRADEABLES[g].getCount()) {
    return emptyItemGrade;
  }
  assert (i >= 0 && i < GRADEABLES[g].getCount());
  std::map<GRADEABLE_ENUM,std::vector<ItemGrade> >::const_iterator itr = all_item_grades.find(g);
  assert (itr != all_item_grades.end());
  assert (int(itr->second.size()) > i);
  
  return itr->second[i]; //return value; 
}



void Student::setGradeableItemGrade(GRADEABLE_ENUM g, int i, float value, 
                                    int late_days_used, const std::string &note, const std::string &status) {
  assert (i >= 0 && i < GRADEABLES[g].getCount());
  std::map<GRADEABLE_ENUM,std::vector<ItemGrade> >::iterator itr = all_item_grades.find(g);
  assert (itr != all_item_grades.end());
  assert (int(itr->second.size()) > i);
  itr->second[i] = ItemGrade(value,late_days_used,note,status);
}



// =============================================================================================
// GRADER CALCULATION HELPER FUNCTIONS

class score_object {
public:
  score_object(float s,float m,float p,float sm):score(s),max(m),percentage(p),scale_max(sm){}
  float score;
  float max;
  float percentage;
  float scale_max;
};

bool operator<(const score_object &a, const score_object &b) {
  return a.score < b.score;
}

float Student::GradeablePercent(GRADEABLE_ENUM g) const {
  if (GRADEABLES[g].getCount() == 0) return 0;
  if (GRADEABLES[g].getMaximum() == 0) return 0;
  assert (GRADEABLES[g].getMaximum() > 0);
  assert (GRADEABLES[g].getPercent() >= 0);

  // special rules for tests
  if (g == GRADEABLE_ENUM::TEST && TEST_IMPROVEMENT_AVERAGING_ADJUSTMENT) {
    return adjusted_test_pct();
  }

  // normalize & drop lowest #
  if (g == GRADEABLE_ENUM::QUIZ && QUIZ_NORMALIZE_AND_DROP > 0) {
    return quiz_normalize_and_drop(QUIZ_NORMALIZE_AND_DROP);
  }

  if (g == GRADEABLE_ENUM::TEST && LOWEST_TEST_COUNTS_HALF) {
    return lowest_test_counts_half_pct();
  }

  // collect the scores in a vector
  std::vector<score_object> scores;
  for (int i = 0; i < GRADEABLES[g].getCount(); i++) {
    float s = getGradeableItemGrade(g,i).getValue();
    std::string id = GRADEABLES[g].getID(i);
    float m = GRADEABLES[g].getMaximum(id);
    float p = GRADEABLES[g].getItemPercentage(id);
    float sm = GRADEABLES[g].getScaleMaximum(id);
    scores.push_back(score_object(s,m,p,sm));
  }

  // sort the scores (smallest first)
  std::sort(scores.begin(),scores.end());

  assert (GRADEABLES[g].getRemoveLowest() >= 0 &&
          GRADEABLES[g].getRemoveLowest() < GRADEABLES[g].getCount());

  // sum the remaining (higher) scores
  float sum_max = 0;
  for (int i = GRADEABLES[g].getRemoveLowest(); i < GRADEABLES[g].getCount(); i++) {
    float m = scores[i].max;
    sum_max += m;
  }

  // sum the remaining (higher) scores
  float sum = 0;
  for (int i = GRADEABLES[g].getRemoveLowest(); i < GRADEABLES[g].getCount(); i++) {
    float s = scores[i].score;
    float m = scores[i].max;
    float p = scores[i].percentage;
    float sm = scores[i].scale_max;
    float my_max = std::max(m,sm);
    if (p < 0) {
      assert (my_max > 0);
      assert (sum_max > 0);
      p = std::max(m,sm) / sum_max;
    }
    sum += p * s / my_max;
  }

  return 100*GRADEABLES[g].getPercent()*sum;
}




float Student::adjusted_test(int i) const {
  assert (i >= 0 && i <  GRADEABLES[GRADEABLE_ENUM::TEST].getCount());
  float a = getGradeableItemGrade(GRADEABLE_ENUM::TEST,i).getValue();
  float b;
  if (i+1 < GRADEABLES[GRADEABLE_ENUM::TEST].getCount()) {
    b = getGradeableItemGrade(GRADEABLE_ENUM::TEST,i+1).getValue();
  } else {
    assert (GRADEABLES[GRADEABLE_ENUM::EXAM].getCount() == 1);
    b = getGradeableItemGrade(GRADEABLE_ENUM::EXAM,0).getValue();
    // HACK  need to scale the final exam!
    b *= 0.6667;
  }

  if (a > b) return a;
  return (a+b) * 0.5;
}


float Student::adjusted_test_pct() const {
  float sum = 0;
  for (int i = 0; i < GRADEABLES[GRADEABLE_ENUM::TEST].getCount(); i++) {
    sum += adjusted_test(i);
  }
  float answer =  100 * GRADEABLES[GRADEABLE_ENUM::TEST].getPercent() * sum / float (GRADEABLES[GRADEABLE_ENUM::TEST].getMaximum());
  return answer;
}



float Student::quiz_normalize_and_drop(int num) const {

  assert (num > 0);

  // collect the normalized quiz scores in a vector
  std::vector<float> scores;
  for (int i = 0; i < GRADEABLES[GRADEABLE_ENUM::QUIZ].getCount(); i++) {
    // the max for this quiz
    float p = PERFECT_STUDENT_POINTER->getGradeableItemGrade(GRADEABLE_ENUM::QUIZ,i).getValue();
    // this students score
    float v = getGradeableItemGrade(GRADEABLE_ENUM::QUIZ,i).getValue();
    scores.push_back(v/p);
  }

  assert (scores.size() > std::size_t(num)); //Relies on the assert(num > 0) at the top of this function.

  // sort the scores
  sort(scores.begin(),scores.end());

  // sum up all but the lowest "num" scores
  float sum = 0;
  for (std::size_t i = num; i < scores.size(); i++) {
    sum += scores[i];
  }

  // the overall percent of the final grade for quizzes
  return 100 * GRADEABLES[GRADEABLE_ENUM::QUIZ].getPercent() * sum / float (scores.size()-num);
}


float Student::lowest_test_counts_half_pct() const {

  int num_tests = GRADEABLES[GRADEABLE_ENUM::TEST].getCount();
  assert (num_tests > 0);

  // first, collect & sort the scores
  std::vector<float> scores;
  for (int i = 0; i < num_tests; i++) {
    scores.push_back(getGradeableItemGrade(GRADEABLE_ENUM::TEST,i).getValue());
  }
  std::sort(scores.begin(),scores.end());

  // then sum the scores 
  float sum = 0.5 * scores[0];
  float weight_total = 0.5;
  for (int i = 1; i < num_tests; i++) {
    sum += scores[i];
    weight_total += 1.0;
  }
  
  // renormalize!
  sum *= float(num_tests) / weight_total;
  
  // scale to percent;
  return 100 * GRADEABLES[GRADEABLE_ENUM::TEST].getPercent() * sum / float (GRADEABLES[GRADEABLE_ENUM::TEST].getMaximum());
}


// =============================================================================================
// =============================================================================================

int Student::getAllowedLateDays(int which_lecture) const {
  if (getSection() == 0) return 0;
  
  //int answer = 2;

  int answer = default_allowed_late_days;
  
  // average 4 questions per lecture 2-28 ~= 112 clicker questions
  //   30 questions => 3 late days
  //   60 questions => 4 late days
  //   90 qustions  => 5 late days
  
  float total = getIClickerTotal(which_lecture,0);
  
  for (unsigned int i = 0; i < GLOBAL_earned_late_days.size(); i++) {
    if (total >= GLOBAL_earned_late_days[i]) {
      answer++;
    }
  }

  for (unsigned int i = 0; i < bonus_late_days_which_lecture.size(); i++) {
    if (bonus_late_days_which_lecture[i] <= which_lecture) {
      answer++;
    }
  }
  
  return std::max(current_allowed_late_days,answer);

}

// get the total used late days
int Student::getUsedLateDays() const {
  int answer = 0;
  for (std::map<GRADEABLE_ENUM,std::vector<ItemGrade> >::const_iterator itr = all_item_grades.begin(); itr != all_item_grades.end(); itr++) {
    for (std::size_t i = 0; i < itr->second.size(); i++) {
      answer += itr->second[i].getLateDaysUsed();
    }
  }
  return answer;
}

// =============================================================================================

float Student::overall_b4_moss() const {
  float answer = 0;
  for (unsigned int i = 0; i < ALL_GRADEABLES.size(); i++) { 
    GRADEABLE_ENUM g = ALL_GRADEABLES[i];
    answer += GradeablePercent(g);
  }
  return answer;
}

std::string Student::grade(bool flag_b4_moss, Student *lowest_d) const {

  if (section == 0) return "";

  if (!flag_b4_moss && manual_grade != "") return manual_grade;
  
  float over = overall();
  if (flag_b4_moss) {
    over = overall_b4_moss();
  }


  // some criteria that might indicate automatica failure of course
  // (instructor can override with manual grade)
  int failed_lab   = (GradeablePercent(GRADEABLE_ENUM::LAB)       < 1.01 * lowest_d->GradeablePercent(GRADEABLE_ENUM::LAB)       ) ? true : false;
  int failed_hw    = (GradeablePercent(GRADEABLE_ENUM::HOMEWORK)  < 0.95 * lowest_d->GradeablePercent(GRADEABLE_ENUM::HOMEWORK)  ) ? true : false;
  int failed_testA = (GradeablePercent(GRADEABLE_ENUM::TEST)      < 0.90 * lowest_d->GradeablePercent(GRADEABLE_ENUM::TEST)      ) ? true : false;
  int failed_testB = (GradeablePercent(GRADEABLE_ENUM::EXAM)      < 0.90 * lowest_d->GradeablePercent(GRADEABLE_ENUM::EXAM)      ) ? true : false;
  int failed_testC = (GradeablePercent(GRADEABLE_ENUM::TEST) + GradeablePercent(GRADEABLE_ENUM::EXAM) < 
                      0.90 * lowest_d->GradeablePercent(GRADEABLE_ENUM::TEST) + lowest_d->GradeablePercent(GRADEABLE_ENUM::EXAM) ) ? true : false;
  if (failed_lab || failed_hw ||
      ( failed_testA +
        failed_testB +
        failed_testC ) > 1) {
    //std::cout << "SHOULD AUTO FAIL";

    //((Student*)this)->other_note += "SHOULD AUTO FAIL";
    return "F";
  }
  

  // otherwise apply the cutoffs
  if (over >= CUTOFFS["A"])  return "A";
  if (over >= CUTOFFS["A-"]) return "A-";
  if (over >= CUTOFFS["B+"]) return "B+";
  if (over >= CUTOFFS["B"])  return "B";
  if (over >= CUTOFFS["B-"]) return "B-";
  if (over >= CUTOFFS["C+"]) return "C+";
  if (over >= CUTOFFS["C"])  return "C";
  if (over >= CUTOFFS["C-"]) return "C-";
  if (over >= CUTOFFS["D+"]) return "D+";
  if (over >= CUTOFFS["D"])  return "D";
  else return "F";

  return "?";
}



void Student::mossify(const std::string &gradeable, float penalty) {

  // if the penalty is "a whole or partial letter grade"....
  float average_letter_grade = (CUTOFFS["A"]-CUTOFFS["B"] +
                                 CUTOFFS["B"]-CUTOFFS["C"] +
                                 CUTOFFS["C"]-CUTOFFS["D"]) / 3.0;

  assert (GRADEABLES[GRADEABLE_ENUM::HOMEWORK].hasCorrespondence(gradeable));
  int which = GRADEABLES[GRADEABLE_ENUM::HOMEWORK].getCorrespondence(gradeable).first;

  if (!(getGradeableItemGrade(GRADEABLE_ENUM::HOMEWORK,which).getValue() > 0)) {
    std::cerr << "WARNING:  the grade for this homework is already 0, moss penalty error?" << std::endl;
  }
  setGradeableItemGrade(GRADEABLE_ENUM::HOMEWORK,which,0);

  // the penalty is positive
  // but it will be multiplied by a negative and added to the total;
  assert (penalty >= 0);

  moss_penalty += -0.0000001;
  moss_penalty += -average_letter_grade * penalty;

  addWarning("[MOSS PENALTY " + std::to_string(penalty) + "]");
}



void Student::ManualGrade(const std::string &grade, const std::string &message) {
  assert (grade == "A" ||
          grade == "A-" ||
          grade == "B+" ||
          grade == "B" ||
          grade == "B-" ||
          grade == "C+" ||
          grade == "C" ||
          grade == "C-" ||
          grade == "D+" ||
          grade == "D" ||
          grade == "F");
  manual_grade = grade;
  other_note += "awarding a " + grade + " because " + message;
}


void Student::outputgrade(std::ostream &ostr,bool flag_b4_moss,Student *lowest_d) const {
  std::string g = grade(flag_b4_moss,lowest_d);
  
  std::string color = GradeColor(g);
  if (moss_penalty < -0.01) {
    ostr << "<td align=center bgcolor=" << color << ">" << g << " @</td>";
  } else {
    ostr << "<td align=center bgcolor=" << color << ">" << g << "</td>";
  }
}


// =============================================================================================

// zones for exams...
//const std::string& Student::getZone(int i) const {
std::string Student::getZone(int i) const {
  assert (i >= 0 && i < GRADEABLES[GRADEABLE_ENUM::TEST].getCount()); return zones[i]; 
}



// =============================================================================================


bool iclickertotalhelper(const std::string &clickername,int which_lecture) {
  std::stringstream ss(clickername);
  int foo;
  ss >> foo;
  if (foo <= which_lecture) return true;
  return false;
}


void Student::addIClickerAnswer(const std::string& which_question, char which_answer, iclicker_answer_enum grade) { 
  iclickeranswers[which_question] = std::make_pair(which_answer,grade);  }

float Student::getIClickerRecent() const {
  if (getUserName() == "PERFECT") { return std::min((int)ICLICKER_QUESTION_NAMES.size(),ICLICKER_RECENT); }
  return getIClickerTotal(100, std::max(0,(int)ICLICKER_QUESTION_NAMES.size()-ICLICKER_RECENT));
}

float Student::getIClickerTotal(int which_lecture, int start) const {
  if (getUserName() == "PERFECT") { return MAX_ICLICKER_TOTAL; } 
  float ans = 0;
  for (unsigned int i = start; i < ICLICKER_QUESTION_NAMES.size(); i++) {
    std::map<std::string,std::pair<char,iclicker_answer_enum> >::const_iterator itr = iclickeranswers.find(ICLICKER_QUESTION_NAMES[i]);
    if (itr == iclickeranswers.end()) continue;
    if (!iclickertotalhelper(itr->first,which_lecture)) continue;
    if (itr->second.second == ICLICKER_CORRECT ||
        itr->second.second == ICLICKER_PARTICIPATED) {
      ans += 1.0;
    } else if (itr->second.second == ICLICKER_INCORRECT) {
      ans += 0.5;
    }
  }
  return ans;
}

std::pair<std::string,iclicker_answer_enum> Student::getIClickerAnswer(const std::string& which_question) const {
  std::pair<std::string,iclicker_answer_enum> noanswer = std::make_pair("",ICLICKER_NOANSWER);
  std::map<std::string,std::pair<char,iclicker_answer_enum> >::const_iterator itr = iclickeranswers.find(which_question); 
  if (itr == iclickeranswers.end()) return noanswer;
  
  char x = itr->second.first;
  std::string tmp(1,x);
  iclicker_answer_enum val = itr->second.second;
  return std::make_pair(tmp,val);
}

// =============================================================================================
