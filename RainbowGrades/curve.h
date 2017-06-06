#include <cassert>
#include <iostream>
#include <string>
#include <vector>


class Curve {

public:

  // =========================
  // CONSTRUCTOR
  Curve() {
    add("PERFECT",-1,-1);
    add("AVERAGE",-1,-1);
  }

  static Curve* DataStructuresCurve() {
    Curve *curve = new Curve();
    curve->add("LOWEST A-", 0.30,  -1);
    curve->add("LOWEST B-", 0.30,  -1);
    curve->add("LOWEST C-", 0.30,  -1);
    curve->add("LOWEST D",    -1, 0.5);
    return curve;
  }


  static Curve* TypicalCurve() {
    Curve *curve = new Curve();
    curve->add("LOWEST A-",   -1, 0.9);
    curve->add("LOWEST B-",   -1, 0.8);
    curve->add("LOWEST C-",   -1, 0.7);
    curve->add("LOWEST D",    -1, 0.6);
    return curve;
  }

  // =========================
  // ACCESSORS

  int num() const {
    assert (names.size() == target_distributions.size() && names.size() == fixed_percentages.size());
    return names.size();
  }
    
  const std::string& getName(int i) const {
    assert (i >= 0 && i < num());
    return names[i];
  }
  float getTargetDistribution(int i) const {
    assert (i >= 0 && i < num());
    return target_distributions[i];
  }
  float getFixedPercentage(int i) const {
    assert (i >= 0 && i < num());
    return fixed_percentages[i];
  }

  // =========================
  // MODIFIER
  void add(const std::string &name, float distribution, float percentage) {
    names.push_back(name);
    target_distributions.push_back(distribution);
    fixed_percentages.push_back(percentage);
  }


private:

  // =========================
  // REPRESENTATION

  //  e.g. "perfect", "average", "lowest a-", etc.
  std::vector<std::string> names;

  // e.g., 30% A/A-, 30% B+/B/B-, 30% C+/C/C-, 10% D/F
  std::vector<float> target_distributions;

  // e.g., 90% / 80% / 70% / 60%
  std::vector<float> fixed_percentages;

};
