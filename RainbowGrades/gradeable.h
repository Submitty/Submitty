#ifndef _GRADEABLE_H_
#define _GRADEABLE_H_

#include <string>
#include <vector>
#include <map>


enum class GRADEABLE_ENUM { 
  HOMEWORK, ASSIGNMENT, PROBLEM_SET,
    QUIZ, TEST, EXAM, 
    EXERCISE, LECTURE_EXERCISE, READING, LAB, RECITATION,
    PROJECT, PARTICIPATION, NOTE,
    NONE };


inline std::string gradeable_to_string(const GRADEABLE_ENUM &g) {
  if (g == GRADEABLE_ENUM::HOMEWORK)         { return "HOMEWORK"; }
  if (g == GRADEABLE_ENUM::ASSIGNMENT)       { return "ASSIGNMENT"; }
  if (g == GRADEABLE_ENUM::PROBLEM_SET)      { return "PROBLEM_SET"; }
  if (g == GRADEABLE_ENUM::QUIZ)             { return "QUIZ"; }
  if (g == GRADEABLE_ENUM::TEST)             { return "TEST"; }
  if (g == GRADEABLE_ENUM::EXAM)             { return "EXAM"; }
  if (g == GRADEABLE_ENUM::EXERCISE)         { return "EXERCISE"; }
  if (g == GRADEABLE_ENUM::LECTURE_EXERCISE) { return "LECTURE_EXERCISE"; }
  if (g == GRADEABLE_ENUM::READING)          { return "READING"; }
  if (g == GRADEABLE_ENUM::LAB)              { return "LAB"; }
  if (g == GRADEABLE_ENUM::RECITATION)       { return "RECITATION"; }
  if (g == GRADEABLE_ENUM::PROJECT)          { return "PROJECT"; }
  if (g == GRADEABLE_ENUM::PARTICIPATION)    { return "PARTICIPATION"; }
  if (g == GRADEABLE_ENUM::NOTE)             { return "NOTE"; }
  if (g == GRADEABLE_ENUM::NONE)             { return "NONE"; } 
  std::cerr << "ERROR!  UNKNOWN GRADEABLE" << std::endl;
  exit(0);
}

inline std::string spacify(const std::string &s) {
  std::string tmp = "";
  for (int i = 0; i < s.size(); i++) {
    tmp += std::string(1,s[i]) + " ";
  }
  return tmp;
}
  

// ===============================================================================

class Gradeable {

public:

  // CONSTRUTORS
  Gradeable() { count=0;percent=0;remove_lowest=0; }
  Gradeable(int c, float p) : count(c),percent(p) { remove_lowest=0; }

  // ACCESSORS
  int getCount() const { return count; }
  float getPercent() const { return percent; }
  float getMaximum() const { 
    if (maximums.size() == 0) return 0;
    assert (maximums.size() > 0);
    int max_sum = 0;
    for (std::map<std::string,float>::const_iterator itr = maximums.begin();
         itr != maximums.end(); itr++) {
      max_sum += itr->second;
    }
    return max_sum * getCount() / maximums.size();
  }
  int getRemoveLowest() const { return remove_lowest; }

  std::string getID(int index) const {
    std::map<std::string,std::pair<int,std::string> >::const_iterator itr = correspondences.begin();
    while (itr != correspondences.end()) {
      if (itr->second.first == index) return itr->first;
      itr++;
    }
    return "";
  }

  bool hasCorrespondence(const std::string &id) const {
    /*
    for (std::map<std::string,std::pair<int,std::string> >::const_iterator itr = correspondences.begin();
         itr != correspondences.end(); itr++) {
      std::cout << "looking for " << id << " " << itr->first << std::endl;
    }
    */
    std::map<std::string,std::pair<int,std::string> >::const_iterator itr =  correspondences.find(id);
    return (itr != correspondences.end());
  }

  const std::pair<int,std::string>& getCorrespondence(const std::string& id) {
    assert (hasCorrespondence(id));
    return correspondences.find(id)->second;
  }

  bool isReleased(const std::string &id) const {
    assert (released.find(id) != released.end());
    return released.find(id)->second;
  }
  float getMaximum(const std::string &id) const {
    assert (maximums.find(id) != maximums.end());
    return maximums.find(id)->second;
  }
  float getScaleMaximum(const std::string &id) const {
    if (scale_maximums.find(id) == scale_maximums.end()) {
      return -1;
    }
    return scale_maximums.find(id)->second;
  }
  float getItemPercentage(const std::string &id) const {
    if (item_percentages.find(id) == item_percentages.end())
      return -1;
    else
      return item_percentages.find(id)->second;
  }
  float getClamp(const std::string &id) const {
    assert (clamps.find(id) != clamps.end());
    return clamps.find(id)->second;
  }

  // MODIFIERS
  void setRemoveLowest(int r) { remove_lowest=r; }

  int setCorrespondence(const std::string& id) {
    assert (!hasCorrespondence(id));
    //std::cout << "SET CORR " << id << std::endl;
    assert (int(correspondences.size()) < count);
    int index = correspondences.size();
    correspondences[id] = std::make_pair(index,"");
    return index;
  }

  void setCorrespondenceName(const std::string& id, const std::string& name) {
    assert (hasCorrespondence(id));
    assert (correspondences[id].second == "");
    correspondences[id].second = name;
  }

  void setReleased(const std::string&id, bool is_released) {
    assert (hasCorrespondence(id));
    assert (released.find(id) == released.end());
    released[id] = is_released;
  }

  void setMaximum(const std::string&id, float maximum) {
    assert (hasCorrespondence(id));
    assert (maximums.find(id) == maximums.end());
    maximums[id] = maximum;
  }
  void setScaleMaximum(const std::string&id, float scale_maximum) {
    assert (hasCorrespondence(id));
    assert (scale_maximums.find(id) == scale_maximums.end());
    scale_maximums[id] = scale_maximum;
  }
  void setItemPercentage(const std::string&id, float item_percentage) {
    assert (hasCorrespondence(id));
    assert (item_percentages.find(id) == item_percentages.end());
    item_percentages[id] = item_percentage;
  }
  void setClamp(const std::string&id, float clamp) {
    assert (hasCorrespondence(id));
    assert (clamps.find(id) == clamps.end());
    clamps[id] = clamp;
  }

private:

  // REPRESENTATION
  int count;
  float percent;
  int remove_lowest;
  std::map<std::string,std::pair<int,std::string> > correspondences;
  std::map<std::string,float> maximums;
  std::map<std::string,float> scale_maximums;
  std::map<std::string,float> item_percentages;
  std::map<std::string,float> clamps;
  std::map<std::string,bool> released;
};

// ===============================================================================

extern std::vector<GRADEABLE_ENUM>    ALL_GRADEABLES;

extern std::map<GRADEABLE_ENUM,Gradeable>  GRADEABLES;


inline void LookupGradeable(const std::string &id,
                     GRADEABLE_ENUM &g_e, int &i) {
  for (std::size_t k = 0; k < ALL_GRADEABLES.size(); k++) {
    GRADEABLE_ENUM e = ALL_GRADEABLES[k];
    Gradeable g = GRADEABLES[e];
    if (g.hasCorrespondence(id)) {
      g_e = e;
      i = g.getCorrespondence(id).first;
      return;
    }
  }
  assert (0);
}


#endif
