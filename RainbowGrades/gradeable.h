#ifndef _GRADEABLE_H_
#define _GRADEABLE_H_

#include <string>
#include <vector>
#include <map>


enum class GRADEABLE_ENUM { NONE, READING, EXERCISE, LAB, PARTICIPATION, HOMEWORK, PROJECT, QUIZ, TEST, EXAM };


inline std::string gradeable_to_string(const GRADEABLE_ENUM &g) {
  if (g == GRADEABLE_ENUM::READING)       { return "READING"; }
  if (g == GRADEABLE_ENUM::EXERCISE)      { return "EXERCISE"; }
  if (g == GRADEABLE_ENUM::LAB)           { return "LAB"; }
  if (g == GRADEABLE_ENUM::PARTICIPATION) { return "PARTICIPATION"; }
  if (g == GRADEABLE_ENUM::HOMEWORK)      { return "HOMEWORK"; }
  if (g == GRADEABLE_ENUM::PROJECT)       { return "PROJECT"; }
  if (g == GRADEABLE_ENUM::QUIZ)          { return "QUIZ"; }
  if (g == GRADEABLE_ENUM::TEST)          { return "TEST"; }
  if (g == GRADEABLE_ENUM::EXAM)          { return "EXAM"; }
  std::cerr << "ERROR!  UNKNOWN GRADEABLE" << std::endl;
  exit(0);
}


// ===============================================================================

class Gradeable {

public:

  // CONSTRUTORS
  Gradeable() { count=0;percent=0;maximum=0; remove_lowest=0; }
  Gradeable(int c, float p, float m) : count(c),percent(p),maximum(m) { remove_lowest=0; }

  // ACCESSORS
  int getCount() const { return count; }
  float getPercent() const { return percent; }
  float getMaximum() const { return maximum; }
  int getRemoveLowest() const { return remove_lowest; }

  std::string getID(int index) const {
    std::map<std::string,std::pair<int,std::string> >::const_iterator itr = correspondences.begin();
    while (itr != correspondences.end()) {
      if (itr->second.first == index) return itr->first;
      itr++;
    }
    return "";
  }

  bool hasCorrespondence(const std::string &id) {
    return (correspondences.find(id) != correspondences.end());
  }

  const std::pair<int,std::string>& getCorrespondence(const std::string& id) {
    assert (hasCorrespondence(id));
    return correspondences.find(id)->second;
  }

  // MODIFIERS
  void setRemoveLowest(int r) { remove_lowest=r; }

  int setCorrespondence(const std::string& id) {
    assert (!hasCorrespondence(id));
    assert (correspondences.size() < count);
    int index = correspondences.size();
    correspondences[id] = std::make_pair(index,"");
    return index;
  }

  void setCorrespondenceName(const std::string& id, const std::string& name) {
    assert (hasCorrespondence(id));
    assert (correspondences[id].second == "");
    correspondences[id].second = name;
  }


private:

  // REPRESENTATION
  int count;
  float percent;
  float maximum;
  int remove_lowest;
  std::map<std::string,std::pair<int,std::string> > correspondences;
};

// ===============================================================================

extern std::vector<GRADEABLE_ENUM>    ALL_GRADEABLES;

extern std::map<GRADEABLE_ENUM,Gradeable>  GRADEABLES;


#endif
