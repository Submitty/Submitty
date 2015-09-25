#ifndef _GRADE_H_
#define _GRADE_H_

#include <string>

class Grade {
public:
  Grade(const std::string &v) : value(v) {}
  std::string value;
};


bool operator< (const Grade &a, const Grade &b);

#endif
