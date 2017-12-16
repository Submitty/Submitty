
#ifndef _STUDENT_H_
#define _STUDENT_H_

#include <iostream>
#include <vector>
#include <string>
#include <cassert>
#include <map>
#include <algorithm>
#include <sstream>

#include "iclicker.h"
#include "gradeable.h"
#include "constants_and_globals.h"

//====================================================================
//====================================================================
// stores the grade for a single gradeable

class ItemGrade {
public:
  ItemGrade(float v, int ldu=0, const std::string& n="", const std::string &s="") {
    value = v;
    late_days_used = ldu;
    note = n;
    if (s != "UNKONWN") {
      status = s;
    }
  }
  float getValue() const { 

    float adjusted_value = value;
    if (late_days_used > 0) {
      //std::cout << "LATE DAYS! " << late_days_used << std::endl;
      // FIXME:  Currently a flat penalty no matter how many days used
      adjusted_value = (1-LATE_DAY_PERCENTAGE_PENALTY)*value;
      //std::cout << "value " << value << "-> " << adjusted_value << std::endl;
    }
    
    /*
    // grab the maximum score for this homework
    assert (PERFECT_STUDENT_POINTER != NULL);
    std::map<GRADEABLE_ENUM,std::vector<ItemGrade> >::const_iterator ps_itr = PERFECT_STUDENT_POINTER->all_item_grades.find(g);
    assert (ps_itr != all_item_grades.end());
    float ps_value = ps_itr->second[i].getValue();
    */
    // adjust the homework score
    //value = std::max(0.0f, value - d*LATE_DAY_PERCENTAGE_PENALTY*ps_value);

    return adjusted_value; 
  }
  int getLateDaysUsed() const { return late_days_used; }
  const std::string& getNote() const { return note; }
  const std::string& getStatus() const { return status; }
private:
  float value;
  int late_days_used;
  std::string note;
  std::string status;
};

//====================================================================
//====================================================================

class Student {

public:

  // ---------------
  // CONSTRUCTOR
  Student();

  // ---------------
  // ACCESSORS

  // personal data
  const std::string& getUserName()      const { return username; }
  const std::string& getFirstName()     const { return legal_first; }
  const std::string& getPreferredName() const { if (preferred_first != "") return preferred_first; return legal_first; }
  const std::string& getLastName()      const { return last; }
  const std::string& getLastUpdate()    const { return lastUpdate; }
  bool getLefty() const { return lefty; }
  
  // registration status
  int getSection()           const { return section; }
  bool getAudit()            const { return audit; }
  bool getWithdraw()         const { return withdraw; }
  bool getIndependentStudy() const { return independentstudy; }

  // grade data
  const ItemGrade& getGradeableItemGrade(GRADEABLE_ENUM g, int i) const;
  std::string getZone(int i) const;
  int getAllowedLateDays(int which_lecture) const;
  int getUsedLateDays() const;
  float getMossPenalty() const { return moss_penalty; }

  void setCurrentAllowedLateDays(int d) { current_allowed_late_days = d; }
  void setDefaultAllowedLateDays(int d) { default_allowed_late_days = d; }

  int getDefaultAllowedLateDays() const { return default_allowed_late_days; }

  void add_bonus_late_day(int which_lecture) { bonus_late_days_which_lecture.push_back(which_lecture); }

  // other grade-like data
  const std::vector<std::string>& getRemoteID() const { return remote_id; }
  bool getAcademicIntegrityForm()  const { return academic_integrity_form; }
  int getParticipation()           const { return participation; }
  int getUnderstanding()           const { return understanding; }

  // info about exam assignments
  const std::string& getExamRoom() const { return exam_room; }
  const std::string& getExamZone() const { return exam_zone; }
  std::string getExamRow() const { return exam_row;}
  std::string getExamSeat() const { return exam_seat;}
  const std::string& getExamTime() const { return exam_time; }
  const std::string& getExamZoneImage() const { return exam_zone_image; }

  // per student notes
  const std::string& getRecommendation()          const { return ta_recommendation; }
  const std::string& getOtherNote()                  const { return other_note; }
  const std::vector<std::string>& getEarlyWarnings() const { return early_warnings; }

  // ---------------
  // MODIFIERS

  // personal data
  void setUserName(const std::string &s)      { username=s; }
  void setLegalFirstName(const std::string &s)     { legal_first=s; }
  void setPreferredFirstName(const std::string &s) { preferred_first=s; }
  void setLastName(const std::string &s)      { last=s; }
  void setLefty() { lefty = true; }
  void setLastUpdate(const std::string &s)    { lastUpdate = s; }

  // registration status
  void setSection(int x) { section = x; }
  void setAudit() { audit = true; }
  void setWithdraw() { withdraw = true; }
  void setIndependentStudy() { independentstudy = true; }

  // grade data
  void setTestZone(int which_test, const std::string &zone)  { zones[which_test] = zone; }
  void setGradeableItemGrade(GRADEABLE_ENUM g, int i, float value, int late_days_used=0, const std::string &note="",const std::string &status="");

  void mossify(const std::string &gradeable, float penalty);

  // other grade-like data
  void setRemoteID(const std::string& r_id) { remote_id.push_back(r_id); }
  void setAcademicIntegrityForm() { academic_integrity_form = true; }
  void setParticipation(int x) { participation = x; }
  void setUnderstanding(int x) { understanding = x; }

  // info about exam assignments
  void setExamRoom(const std::string &s) { exam_room = s; }
  void setExamZone(const std::string &z, const std::string &r, const std::string &s) { exam_zone=z; exam_row=r; exam_seat=s; }
  void setExamTime(const std::string &s) { exam_time = s; }
  void setExamZoneImage(const std::string &s) { exam_zone_image = s; }

  // per student notes
  void addWarning(const std::string &message) { early_warnings.push_back(message); }
  void addRecommendation(const std::string &message) { ta_recommendation += message; }
  void addNote(const std::string &message) { other_note += message; }
  void ManualGrade(const std::string &grade, const std::string &message);


  // ---------------
  // I-CLICKER
  
  void addIClickerAnswer(const std::string& which_question, char which_answer, iclicker_answer_enum grade);
  float getIClickerRecent() const;
  float getIClickerTotalFromStart() const { return getIClickerTotal(100,0);  }
  float getIClickerTotal(int which_lecture, int start) const;
  bool hasPriorityHelpStatus() const { 
    return (getIClickerRecent() >= ICLICKER_PRIORITY * float (ICLICKER_RECENT));  
  }
  std::pair<std::string,iclicker_answer_enum>  getIClickerAnswer(const std::string& which_question) const;


  // HELPER FUNCTIONS
  float GradeablePercent(GRADEABLE_ENUM g) const;
  float overall() const { return overall_b4_moss() + moss_penalty; }
  float adjusted_test(int i) const;
  float adjusted_test_pct() const;
  float lowest_test_counts_half_pct() const;
  float quiz_normalize_and_drop(int num) const;
  float overall_b4_moss() const;
  std::string grade(bool flag_b4_moss, Student *lowest_d) const;
  void outputgrade(std::ostream &ostr,bool flag_b4_moss,Student *lowest_d) const;
  
private:

  // ---------------
  // REPRESENTATION

  // personal data
  std::string username;
  std::string legal_first;
  std::string preferred_first;
  std::string last;
  bool lefty;
  
  std::string lastUpdate;

  int current_allowed_late_days;
  int default_allowed_late_days;

    // registration status
  int section;
  bool audit;
  bool withdraw;
  bool independentstudy;

  // grade data
  std::map<GRADEABLE_ENUM,std::vector<ItemGrade> > all_item_grades;
  std::map<std::string,std::pair<char,iclicker_answer_enum> > iclickeranswers;
  
  std::vector<std::string> zones;
  float moss_penalty;
  float cached_hw;

  // other grade-like data
  std::vector<std::string> remote_id;
  bool academic_integrity_form;
  int participation;
  int understanding;

  std::vector<int> bonus_late_days_which_lecture;

  // info about exam assignments
  std::string exam_zone;
  std::string exam_row;
  std::string exam_seat;
  std::string exam_room;
  std::string exam_time;
  std::string exam_zone_image;

  // per student notes 
  std::string ta_recommendation;
  std::string other_note;
  std::string manual_grade;
  std::vector<std::string> early_warnings;
};

//====================================================================
//====================================================================

Student* GetStudent(const std::vector<Student*> &students, const std::string& username);

#endif
