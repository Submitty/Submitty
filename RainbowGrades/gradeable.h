#ifndef _GRADEABLE_H_
#define _GRADEABLE_H_

#include <string>


enum class GRADEABLE_ENUM { READING, EXERCISE, LAB, PARTICIPATION, HOMEWORK, PROJECT, QUIZ, TEST, EXAM };


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


#endif
